const { Modal, Button } = wp.components;
const { dispatch, useSelect } = wp.data;
import { __ } from "@wordpress/i18n";
import ProBadge from "@/admin/blocks/shared/components/ProBadge";

export default function () {
  const closeModal = () => {
    dispatch("presto-player/player").setProModal(false);
  };

  const open = useSelect((select) => {
    return select("presto-player/player").proModal();
  });

  return open ? (
    <Modal title={__("Pro Feature", "presto-player")} onRequestClose={closeModal}>
      <h2>
        {__("Unlock Presto Player", "presto-player")} <ProBadge />
      </h2>
      <p>{__("Get this feature and more with the Pro version of Presto Player!", "presto-player")}</p>
      <Button href="https://prestoplayer.com" target="_blank" isPrimary>
        {__("Learn More", "presto-player")}
      </Button>
    </Modal>
  ) : (
    ""
  );
}
