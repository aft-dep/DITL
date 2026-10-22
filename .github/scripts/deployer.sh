#!/usr/bin/env bash
#
# Deploiement des fichiers versionnes vers un hebergement Gandi, par sFTP avec
# cle.
#
# Mode incremental (par defaut) : seuls les fichiers ajoutes, modifies ou
# supprimes depuis la derniere revision deployee sont transmis. Cette revision
# est conservee sur le serveur, hors de la racine web (.revision-deployee).
#
# Mise en service sur un environnement deja a jour : deposer a la main sur le
# serveur le fichier .revision-deployee contenant le commit qui correspond aux
# fichiers en place ; le deploiement suivant n'envoie que la difference.
#
# Mode complet (COMPLET=true, lancement manuel) : envoi de tous les fichiers
# versionnes, SANS AUCUNE SUPPRESSION, puis enregistrement de la revision
# deployee. Pour une remise a niveau ; long (plusieurs dizaines de minutes),
# il n'est jamais declenche automatiquement.
#
# Ne sont JAMAIS touches : les fichiers non versionnes du serveur
# (wp-config.php, .htaccess, .user.ini, uploads, modules e-learning...). Seul
# le cache de pages (wp-content/cache/all) est vide apres chaque envoi.
#
# Les operations en base de donnees (desinstallation d'une extension, scripts
# WP-CLI du theme, mise a jour de la base) restent manuelles, en console, et se
# font AVANT le deploiement qui retire les fichiers correspondants.
#
# Variables d'environnement attendues :
#   SFTP_HOTE        serveur sFTP
#   SFTP_HOTE_CONNU  ligne known_hosts du serveur (verification de son identite)
#   SFTP_UTILISATEUR identifiant sFTP
#   SFTP_CLE         cle privee de deploiement
#   SFTP_RACINE      dossier de l'environnement sur le serveur (contient htdocs/)
#   COMPLET          "true" pour un envoi complet (defaut : incremental)

set -euo pipefail

: "${SFTP_HOTE:?SFTP_HOTE manquant}"
: "${SFTP_HOTE_CONNU:?SFTP_HOTE_CONNU manquant}"
: "${SFTP_UTILISATEUR:?SFTP_UTILISATEUR manquant}"
: "${SFTP_CLE:?SFTP_CLE manquant}"
: "${SFTP_RACINE:?SFTP_RACINE manquant}"
COMPLET="${COMPLET:-false}"

RACINE="${SFTP_RACINE%/}"
CIBLE="$RACINE/htdocs"
FICHIER_REVISION="$RACINE/.revision-deployee"
DEPOT="$(git rev-parse --show-toplevel)"
cd "$DEPOT"
REVISION="$(git rev-parse HEAD)"

TRAVAIL="$(mktemp -d)"
trap 'rm -rf "$TRAVAIL"' EXIT
umask 077
printf '%s\n' "$SFTP_CLE" > "$TRAVAIL/cle"
printf '%s\n' "$SFTP_HOTE_CONNU" > "$TRAVAIL/hotes_connus"

erreur() {
	echo "::error::$1"
	exit 1
}

# Fichiers versionnes qui ne partent jamais sur le serveur. Un chemin
# contenant un caractere de controle (retour a la ligne...) est refuse : il
# casserait le script lftp genere ligne par ligne.
est_exclu() {
	case "$1" in
		*[[:cntrl:]]*) erreur "Chemin versionne invalide (caractere de controle) : refus de deployer." ;;
		.github/* | .gitignore | .gitattributes) return 0 ;;
	esac
	return 1
}

# Chaine entre guillemets pour lftp (antislash et guillemet echappes).
quoter() {
	local valeur="${1//\\/\\\\}"
	valeur="${valeur//\"/\\\"}"
	printf '"%s"' "$valeur"
}

# Entete commun des scripts lftp : connexion par cle, identite du serveur
# verifiee, arret a la premiere erreur.
entete_lftp() {
	cat <<EOF
set cmd:fail-exit yes
set net:timeout 30
set net:max-retries 3
set net:reconnect-interval-base 5
set sftp:connect-program "ssh -a -x -i $TRAVAIL/cle -o BatchMode=yes -o IdentitiesOnly=yes -o UserKnownHostsFile=$TRAVAIL/hotes_connus -o StrictHostKeyChecking=yes"
open -u $(quoter "$SFTP_UTILISATEUR,") sftp://$SFTP_HOTE
EOF
}

# Revision deployee sur le serveur, dans la variable DEPLOYEE (vide si
# aucune). Echoue si le serveur est injoignable, pour ne pas confondre une
# panne avec un premier deploiement.
DEPLOYEE=""
lire_revision_deployee() {
	{
		entete_lftp
		echo "cls -1 -a $(quoter "$RACINE/")"
	} > "$TRAVAIL/lister.lftp"
	lftp -f "$TRAVAIL/lister.lftp" > "$TRAVAIL/racine.txt" || erreur "Connexion sFTP impossible ou dossier $RACINE introuvable."

	if ! grep -q '\(^\|/\)\.revision-deployee$' "$TRAVAIL/racine.txt"; then
		return 0
	fi

	{
		entete_lftp
		echo "cat $(quoter "$FICHIER_REVISION")"
	} > "$TRAVAIL/lire.lftp"
	lftp -f "$TRAVAIL/lire.lftp" > "$TRAVAIL/deployee.txt" || erreur "Lecture de $FICHIER_REVISION impossible."
	DEPLOYEE="$(tr -d '[:space:]' < "$TRAVAIL/deployee.txt")"
}

ENVOIS=()
MODIFICATIONS=()
SUPPRESSIONS=()

lire_revision_deployee
echo "Revision a deployer : $REVISION"
echo "Revision deployee sur le serveur : ${DEPLOYEE:-aucune}"

if [ "$COMPLET" = "true" ]; then
	echo "Mode complet : envoi de tous les fichiers versionnes, aucune suppression."
	while IFS= read -r -d '' chemin; do
		est_exclu "$chemin" || ENVOIS+=("$chemin")
	done < <(git ls-files -z)
else
	[ -n "$DEPLOYEE" ] || erreur "Aucune revision deployee sur le serveur : deposer $FICHIER_REVISION (commit correspondant aux fichiers en place) ou lancer un deploiement complet."
	# Revision deposee a la main a la mise en service : un identifiant de
	# commit (et non un nom de branche) connu du depot.
	echo "$DEPLOYEE" | grep -Eqx '[0-9a-f]{7,40}' || erreur "Contenu de $FICHIER_REVISION invalide : un identifiant de commit est attendu."
	DEPLOYEE="$(git rev-parse --verify --quiet "$DEPLOYEE^{commit}")" || erreur "Revision deployee inconnue du depot : deposer la bonne revision ou lancer un deploiement complet."
	git merge-base --is-ancestor "$DEPLOYEE" "$REVISION" || erreur "La revision deployee n'est pas un ancetre de $REVISION (historique reecrit) : deposer la bonne revision ou lancer un deploiement complet."

	if [ "$DEPLOYEE" = "$REVISION" ]; then
		echo "Serveur deja a jour."
		exit 0
	fi

	while IFS= read -r -d '' statut && IFS= read -r -d '' chemin; do
		est_exclu "$chemin" && continue
		case "$statut" in
			D)
				# Fichier retire de git mais desormais ignore (devenu propre a
				# l'environnement) : il reste sur le serveur.
				if git check-ignore -q --no-index "$chemin"; then
					echo "::warning::$chemin est desormais ignore par git : conserve sur le serveur."
				else
					SUPPRESSIONS+=("$chemin")
				fi
				;;
			A) ENVOIS+=("$chemin") ;;
			*) MODIFICATIONS+=("$chemin") ;;
		esac
	done < <(git diff --name-status --no-renames -z "$DEPLOYEE" "$REVISION")

	# Ajouts avant modifications : un fichier modifie qui charge un nouveau
	# fichier (functions.php et un nouveau inc/...) ne part qu'apres lui.
	if [ "${#MODIFICATIONS[@]}" -gt 0 ]; then
		ENVOIS+=("${MODIFICATIONS[@]}")
	fi
fi

echo "Fichiers a envoyer : ${#ENVOIS[@]} ; fichiers a supprimer : ${#SUPPRESSIONS[@]}"

if [ "$COMPLET" = "true" ]; then
	# Copie propre de la revision (sans .git ni fichiers exclus), dates mises a
	# maintenant pour que tout soit transmis par mirror.
	mkdir "$TRAVAIL/export"
	git archive --format=tar "$REVISION" | tar -x -C "$TRAVAIL/export"
	rm -rf "$TRAVAIL/export/.github" "$TRAVAIL/export/.gitignore" "$TRAVAIL/export/.gitattributes"
	find "$TRAVAIL/export" -exec touch {} +
fi

{
	entete_lftp
	echo "lcd $(quoter "$DEPOT")"

	if [ "$COMPLET" = "true" ]; then
		# mirror cree les dossiers et ne supprime rien (pas de --delete).
		echo "mirror -R --parallel=8 --no-perms --verbose=1 $(quoter "$TRAVAIL/export") $(quoter "$CIBLE")"
	else
		# 1. Envois, avant toute suppression : un fichier deplace existe ainsi
		#    toujours a l'un des deux endroits. Les dossiers sont crees au
		#    besoin (sans echec s'ils existent) ; un envoi rate arrete tout,
		#    avant l'enregistrement de la revision : le deploiement suivant
		#    reprend le meme lot.
		if [ "${#ENVOIS[@]}" -gt 0 ]; then
			printf '%s\n' "${ENVOIS[@]}" | sed -n 's#/[^/]*$##p' | sort -u | while IFS= read -r dossier; do
				echo "mkdir -p -f $(quoter "$CIBLE/$dossier")"
			done
			for chemin in "${ENVOIS[@]}"; do
				echo "put $(quoter "$chemin") -o $(quoter "$CIBLE/$chemin")"
			done
		fi

		# 2. Suppressions, sans echec si le fichier a deja disparu (extension
		#    desinstallee en console avant le deploiement), puis dossiers
		#    devenus vides, du plus profond au moins profond. rmdir echoue sans
		#    dommage sur un dossier qui contient encore des fichiers non
		#    versionnes : il est alors conserve.
		if [ "${#SUPPRESSIONS[@]}" -gt 0 ]; then
			echo "set cmd:fail-exit no"
			for chemin in "${SUPPRESSIONS[@]}"; do
				echo "rm -f $(quoter "$CIBLE/$chemin")"
			done
			git ls-tree -r -d --name-only "$REVISION" > "$TRAVAIL/dossiers-conserves.txt"
			printf '%s\n' "${SUPPRESSIONS[@]}" \
				| awk -F/ '{ chemin = $1; for (i = 2; i < NF; i++) { print chemin; chemin = chemin "/" $i } if (NF > 1) print chemin }' \
				| sort -u \
				| grep -vxF -f "$TRAVAIL/dossiers-conserves.txt" \
				| awk -F/ '{ print NF "\t" $0 }' | sort -rn | cut -f2- \
				| while IFS= read -r dossier; do
					echo "rmdir $(quoter "$CIBLE/$dossier")"
				done || true
			echo "set cmd:fail-exit yes"
		fi
	fi

	# 3. Revision deployee (seulement si tout ce qui precede a reussi), puis
	#    cache de pages vide (sans echec s'il est deja vide).
	echo "put $(quoter "$TRAVAIL/revision") -o $(quoter "$FICHIER_REVISION")"
	echo "set cmd:fail-exit no"
	echo "rm -r -f $(quoter "$CIBLE/wp-content/cache/all")"
} > "$TRAVAIL/deployer.lftp"

printf '%s\n' "$REVISION" > "$TRAVAIL/revision"

if [ "${#SUPPRESSIONS[@]}" -gt 0 ]; then
	echo "Suppressions :"
	printf '  %s\n' "${SUPPRESSIONS[@]}"
fi

lftp -f "$TRAVAIL/deployer.lftp"

echo "Deploiement termine : revision $REVISION."
