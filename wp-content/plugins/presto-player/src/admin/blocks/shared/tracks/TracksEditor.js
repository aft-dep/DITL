/**
 * WordPress dependencies
 */
/** @jsx jsx */
// import { __, sprintf } from '@wordpress/i18n';
const { __, sprintf } = wp.i18n;
import { css, jsx, Global } from "@emotion/core";
const {
  NavigableMenu,
  MenuItem,
  FormFileUpload,
  MenuGroup,
  ToolbarGroup,
  ToolbarButton,
  Dropdown,
  Button,
  TextControl,
  Icon,
  Modal,
} = wp.components;
const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
const { useSelect } = wp.data;
const { useState, useRef } = wp.element;
import TranscriptionLanguageSelect from "../components/TranscriptionLanguageSelect";

const ALLOWED_TYPES = ["text/vtt"];

const DEFAULT_KIND = "subtitles";

// const KIND_OPTIONS = [
//   { label: __("Subtitles"), value: "subtitles" },
//   { label: __("Captions"), value: "captions" },
//   { label: __("Descriptions"), value: "descriptions" },
//   { label: __("Chapters"), value: "chapters" },
//   { label: __("Metadata"), value: "metadata" },
// ];

const captionIcon = (
  <svg viewBox="0 0 29 25" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path
      fillRule="evenodd"
      clipRule="evenodd"
      d="M17.5014 20.2854H28.6316V0.764648H0.110825V20.2854H11.241L14.3712 24.2854L17.5014 20.2854ZM14.3712 21.0401L16.5269 18.2854H26.6316V2.76465H2.11082V18.2854H12.2155L14.3712 21.0401Z"
    />
    <path d="M10.4503 14.9446C9.56226 14.9446 8.76226 14.7606 8.05026 14.3926C7.33826 14.0166 6.77826 13.4966 6.37026 12.8326C5.97026 12.1606 5.77026 11.4006 5.77026 10.5526C5.77026 9.70464 5.97026 8.94864 6.37026 8.28464C6.77826 7.61264 7.33826 7.09264 8.05026 6.72464C8.76226 6.34864 9.56226 6.16064 10.4503 6.16064C11.2663 6.16064 11.9943 6.30464 12.6343 6.59264C13.2743 6.88064 13.8023 7.29664 14.2183 7.84064L12.4303 9.43664C11.9103 8.78064 11.2983 8.45264 10.5943 8.45264C10.0023 8.45264 9.52626 8.64464 9.16626 9.02864C8.80626 9.40464 8.62626 9.91264 8.62626 10.5526C8.62626 11.1926 8.80626 11.7046 9.16626 12.0886C9.52626 12.4646 10.0023 12.6526 10.5943 12.6526C11.2983 12.6526 11.9103 12.3246 12.4303 11.6686L14.2183 13.2646C13.8023 13.8086 13.2743 14.2246 12.6343 14.5126C11.9943 14.8006 11.2663 14.9446 10.4503 14.9446Z" />
    <path d="M19.2042 14.9446C18.3162 14.9446 17.5162 14.7606 16.8042 14.3926C16.0922 14.0166 15.5322 13.4966 15.1242 12.8326C14.7242 12.1606 14.5242 11.4006 14.5242 10.5526C14.5242 9.70464 14.7242 8.94864 15.1242 8.28464C15.5322 7.61264 16.0922 7.09264 16.8042 6.72464C17.5162 6.34864 18.3162 6.16064 19.2042 6.16064C20.0202 6.16064 20.7482 6.30464 21.3882 6.59264C22.0282 6.88064 22.5562 7.29664 22.9722 7.84064L21.1842 9.43664C20.6642 8.78064 20.0522 8.45264 19.3482 8.45264C18.7562 8.45264 18.2802 8.64464 17.9202 9.02864C17.5602 9.40464 17.3802 9.91264 17.3802 10.5526C17.3802 11.1926 17.5602 11.7046 17.9202 12.0886C18.2802 12.4646 18.7562 12.6526 19.3482 12.6526C20.0522 12.6526 20.6642 12.3246 21.1842 11.6686L22.9722 13.2646C22.5562 13.8086 22.0282 14.2246 21.3882 14.5126C20.7482 14.8006 20.0202 14.9446 19.2042 14.9446Z" />
  </svg>
);

function TrackList({ tracks, onEditPress }) {
  let content;
  if (tracks.length === 0) {
    content = (
      <p className="block-library-video-tracks-editor__tracks-informative-message">
        {__(
          "Captions are .vtt files that help make your content more accesible to a wider range of users.",
          "presto-player"
        )}
      </p>
    );
  } else {
    content = tracks.map((track, index) => {
      return (
        <div
          key={index}
          className="block-library-video-tracks-editor__track-list-track"
          css={css`
            display: flex;
            justify-content: space-between;
            align-items: center;
          `}
        >
          <span>
            {track.label}
            {track.srcLang ? ` (${track.srcLang})` : ""}
          </span>
          <Button
            isTertiary
            onClick={() => onEditPress(index)}
            aria-label={sprintf(
              /* translators: %s: Label of the video text track e.g: "French subtitles" */
              __("Edit %s", "presto-player"),
              track.label
            )}
          >
            {__("Edit", "presto-player")}
          </Button>
        </div>
      );
    });
  }
  return (
    <MenuGroup
      label={__("Captions", "presto-player")}
      className="block-library-video-tracks-editor__track-list"
    >
      {content}
    </MenuGroup>
  );
}

function SingleTrackEditor({
  track,
  onChange,
  onClose,
  onRemove,
  isBunnyNet = false,
}) {
  const { src = "", label = "", srcLang = "", kind = DEFAULT_KIND } = track;
  const fileName = src.startsWith("blob:")
    ? ""
    : src.substring(src.lastIndexOf("/") + 1);
  return (
    <NavigableMenu>
      <div className="block-library-video-tracks-editor__single-track-editor">
        <span className="block-library-video-tracks-editor__single-track-editor-edit-track-label">
          {__("Edit caption track", "presto-player")}
        </span>
        <span>
          {__("File", "presto-player")}: <b>{fileName}</b>
        </span>
        <div className="block-library-video-tracks-editor__single-track-editor-label-language">
          <TextControl
            /* eslint-disable jsx-a11y/no-autofocus */
            autoFocus
            /* eslint-enable jsx-a11y/no-autofocus */
            onChange={(newLabel) =>
              onChange({
                ...track,
                label: newLabel,
              })
            }
            label={__("Label", "presto-player")}
            value={label}
            help={__("Title of track", "presto-player")}
            disabled={isBunnyNet}
          />
          <TextControl
            onChange={(newSrcLang) =>
              onChange({
                ...track,
                srcLang: newSrcLang,
              })
            }
            label={__("Source language", "presto-player")}
            value={srcLang}
            help={__("Language tag (en, fr, etc.)", "presto-player")}
            disabled={isBunnyNet}
          />
        </div>
        {/* <SelectControl
          className="block-library-video-tracks-editor__single-track-editor-kind-select"
          options={KIND_OPTIONS}
          value={kind}
          label={__("Kind")}
          onChange={(newKind) => {
            if (newKind === DEFAULT_KIND) {
              newKind = undefined;
            }
            onChange({
              ...track,
              kind: newKind,
            });
          }}
        /> */}
        <div
          className="block-library-video-tracks-editor__single-track-editor-buttons-container"
          css={css`
            display: flex;
            gap: 8px;
          `}
        >
          <Button
            isSecondary
            onClick={() => {
              const changes = {};
              let hasChanges = false;
              if (label === "") {
                changes.label = __("English", "presto-player");
                hasChanges = true;
              }
              if (srcLang === "") {
                changes.srcLang = "en";
                hasChanges = true;
              }
              if (hasChanges) {
                onChange({
                  ...track,
                  ...changes,
                });
              }
              onClose();
            }}
          >
            {__("Close", "presto-player")}
          </Button>
          <Button isDestructive isLink onClick={onRemove}>
            {__("Remove track", "presto-player")}
          </Button>
        </div>
      </div>
    </NavigableMenu>
  );
}

export default function TracksEditor({
  tracks = [],
  onChange,
  transcribeLanguages = [],
  onTranscribeLanguagesChange,
  videoId,
  videoType = "public",
}) {
  const mediaUpload = useSelect((select) => {
    return select("core/block-editor").getSettings().mediaUpload;
  }, []);
  const [trackBeingEdited, setTrackBeingEdited] = useState(null);
  const [isTranscribing, setIsTranscribing] = useState(false);
  const [trackToRemove, setTrackToRemove] = useState(null);
  const [isDeletingTrack, setIsDeletingTrack] = useState(false);
  const [showTranscribeConfirm, setShowTranscribeConfirm] = useState(false);
  const [transcribeSuccess, setTranscribeSuccess] = useState(false);
  const [transcribeError, setTranscribeError] = useState(false);
  const dropdownOnCloseRef = useRef(null);

  /**
   * Check if a track is from Bunny.net stream library
   * Uses explicit source flag set by server-side code for robust detection
   */
  const isBunnyNetCaption = (track) => {
    if (!track || !track.source) {
      return false;
    }
    return track.source === "bunny";
  };

  /**
   * Delete a caption from Bunny.net stream library
   */
  const deleteBunnyNetCaption = async (track) => {
    if (!videoId || !track) {
      return;
    }

    try {
      // First, fetch the video data to get the Bunny.net GUID
      const videoData = await wp.apiFetch({
        path: `presto-player/v1/videos/${videoId}`,
      });

      const bunnyGuid = videoData?.external_id;
      if (!bunnyGuid) {
        console.warn(
          "Could not find Bunny.net video GUID for caption deletion"
        );
        return;
      }

      // Get the language code from the track
      const language =
        track.srcLang || track.src?.match(/\/captions\/([^/]+)\.vtt/)?.[1];
      if (!language) {
        console.warn("Could not determine language code for caption deletion");
        return;
      }

      // Delete the caption from Bunny.net
      const deleteCaptionBase =
        prestoPlayerAdmin?.transcriptionEndpoints?.deleteCaption;
      const deletePath = `${deleteCaptionBase}/${bunnyGuid}/${language}`;
      await wp.apiFetch({
        path: wp.url.addQueryArgs(deletePath, {
          type: videoType,
        }),
        method: "DELETE",
      });
    } catch (error) {
      console.error("Error deleting caption from Bunny.net:", error);
      // Show error notice
      wp.data
        .dispatch("core/notices")
        .createNotice(
          "error",
          __(
            "Failed to delete caption from Bunny.net. Please try again.",
            "presto-player"
          ),
          {
            type: "snackbar",
            isDismissible: true,
          }
        );
    }
  };

  /**
   * Handle track removal - show confirmation dialog if Bunny.net caption
   */
  const handleTrackRemove = (trackIndex) => {
    const track = tracks[trackIndex];

    // Close the editor first to prevent accessing undefined track
    setTrackBeingEdited(null);

    // If it's a Bunny.net caption, show confirmation dialog
    if (isBunnyNetCaption(track)) {
      setTrackToRemove({ track, index: trackIndex });
    } else {
      // For non-Bunny.net tracks, remove directly
      onChange(tracks.filter((_track, index) => index !== trackIndex));
    }
  };

  /**
   * Confirm track removal - delete from Bunny.net if applicable
   */
  const confirmTrackRemove = async () => {
    if (!trackToRemove || isDeletingTrack) {
      return;
    }

    const { track, index } = trackToRemove;

    setIsDeletingTrack(true);
    try {
      // If it's a Bunny.net caption, delete it first
      if (isBunnyNetCaption(track)) {
        await deleteBunnyNetCaption(track);
      }

      // Remove from tracks array
      onChange(tracks.filter((_track, i) => i !== index));

      // Close the dialog
      setTrackToRemove(null);
    } finally {
      setIsDeletingTrack(false);
    }
  };

  /**
   * Handle Bunny.net caption generation confirmation
   */
  const handleTranscribe = async () => {
    if (!videoId || !transcribeLanguages.length) {
      return;
    }

    setIsTranscribing(true);
    try {
      const videoData = await wp.apiFetch({
        path: `presto-player/v1/videos/${videoId}`,
      });
      const bunnyGuid = videoData?.external_id;
      if (!bunnyGuid) {
        throw new Error("Could not find Bunny.net video GUID");
      }
      const transcribeEndpoint =
        prestoPlayerAdmin?.transcriptionEndpoints?.transcribe;
      await wp.apiFetch({
        path: transcribeEndpoint,
        method: "POST",
        data: {
          guid: bunnyGuid,
          type: videoType,
          targetLanguages: transcribeLanguages,
        },
      });
      setTranscribeSuccess(true);
    } catch (error) {
      const rawErrorMessage =
        error?.message ||
        __("Failed to generate captions. Please try again.", "presto-player");
      console.error("Bunny transcription API error:", {
        message: rawErrorMessage,
        error: error,
      });
      setTranscribeError(true);
    } finally {
      setIsTranscribing(false);
    }
  };

  /**
   * Get the appropriate modal title based on current state
   */
  const getTranscribeModalTitle = () => {
    if (transcribeSuccess) {
      return __("Caption Generation Started!", "presto-player");
    }
    if (transcribeError) {
      return __("Caption Generation Failed", "presto-player");
    }
    return __("Caption Generation Charges", "presto-player");
  };

  if (!mediaUpload) {
    return null;
  }
  return (
    <>
      {trackToRemove && (
        <>
          <Global
            styles={css`
              .presto-player__modal-confirm-overlay {
                z-index: 1000001 !important;
              }
              .presto-player__modal-confirm-overlay .components-modal__frame {
                z-index: 1000002 !important;
              }
            `}
          />
          <Modal
            className="presto-player__modal-confirm"
            overlayClassName="presto-player__modal-confirm-overlay"
            title={__("Remove Caption Track", "presto-player")}
            onRequestClose={() => setTrackToRemove(null)}
            css={css`
              max-width: 400px;
            `}
          >
            <p>
              {__(
                "Removing this caption track will also delete it from the Bunny.net Stream Library. This action cannot be undone.",
                "presto-player"
              )}
            </p>
            <div
              css={css`
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                margin-top: 16px;
              `}
            >
              <Button
                onClick={() => setTrackToRemove(null)}
                disabled={isDeletingTrack}
              >
                {__("Cancel", "presto-player")}
              </Button>
              <Button
                isDestructive
                onClick={confirmTrackRemove}
                isBusy={isDeletingTrack}
                disabled={isDeletingTrack}
              >
                {__("Remove Track", "presto-player")}
              </Button>
            </div>
          </Modal>
        </>
      )}
      {showTranscribeConfirm && (
        <>
          <Global
            styles={css`
              .presto-player__modal-transcribe-overlay {
                z-index: 1000001 !important;
              }
              .presto-player__modal-transcribe-overlay
                .components-modal__frame {
                z-index: 1000002 !important;
              }
            `}
          />
          <Modal
            className="presto-player__modal-transcribe"
            overlayClassName="presto-player__modal-transcribe-overlay"
            title={getTranscribeModalTitle()}
            onRequestClose={() => {
              setShowTranscribeConfirm(false);
              setTranscribeSuccess(false);
              setTranscribeError(false);
              dropdownOnCloseRef.current?.();
            }}
            css={css`
              max-width: 450px;
            `}
          >
            {transcribeSuccess ? (
              <>
                <p>
                  {__(
                    "Captions are being generated and will automatically appear on the player when ready. Depending on the length of the video, it may take few minutes. Please check back later.",
                    "presto-player"
                  )}
                </p>
                <div
                  css={css`
                    display: flex;
                    justify-content: flex-end;
                    gap: 8px;
                    margin-top: 16px;
                  `}
                >
                  <Button
                    isPrimary
                    onClick={() => {
                      setShowTranscribeConfirm(false);
                      setTranscribeSuccess(false);
                      dropdownOnCloseRef.current?.();
                    }}
                  >
                    {__("Close", "presto-player")}
                  </Button>
                </div>
              </>
            ) : transcribeError ? (
              <>
                <p>
                  {__(
                    "We couldn't process your caption generation request. Please try again. If the issue persists, please re-upload the video to Bunny.net Stream Library or contact support@prestomade.com.",
                    "presto-player"
                  )}
                </p>
                <div
                  css={css`
                    display: flex;
                    justify-content: flex-end;
                    gap: 8px;
                    margin-top: 16px;
                  `}
                >
                  <Button
                    onClick={() => {
                      setShowTranscribeConfirm(false);
                      setTranscribeError(false);
                      dropdownOnCloseRef.current?.();
                    }}
                  >
                    {__("Cancel", "presto-player")}
                  </Button>
                  <Button
                    isPrimary
                    onClick={() => {
                      setTranscribeError(false);
                      handleTranscribe();
                    }}
                  >
                    {__("Try Again", "presto-player")}
                  </Button>
                </div>
              </>
            ) : (
              <>
                <p>
                  {__(
                    "This will automatically generate captions for the selected languages. You will be charged $0.10 per minute for each language from your Bunny.net account balance.",
                    "presto-player"
                  )}
                </p>
                <p
                  css={css`
                    margin-top: 8px;
                  `}
                >
                  <a
                    href="https://docs.bunny.net/docs/stream-pricing#transcribing"
                    target="_blank"
                    rel="noopener noreferrer"
                    css={css`
                      color: var(--wp-admin-theme-color, #007cba);
                      text-decoration: underline;
                      &:hover {
                        color: var(--wp-admin-theme-color-darker-10, #006ba1);
                      }
                    `}
                  >
                    {__("View Bunny.net pricing details", "presto-player")} →
                  </a>
                </p>
                <div
                  css={css`
                    display: flex;
                    justify-content: flex-end;
                    gap: 8px;
                    margin-top: 16px;
                  `}
                >
                  <Button
                    onClick={() => {
                      setShowTranscribeConfirm(false);
                      setTranscribeSuccess(false);
                      setTranscribeError(false);
                      dropdownOnCloseRef.current?.();
                    }}
                    disabled={isTranscribing}
                  >
                    {__("Cancel", "presto-player")}
                  </Button>
                  <Button
                    isPrimary
                    onClick={handleTranscribe}
                    isBusy={isTranscribing}
                    disabled={isTranscribing}
                  >
                    {__("Confirm & Generate", "presto-player")}
                  </Button>
                </div>
              </>
            )}
          </Modal>
        </>
      )}
      <Dropdown
        contentClassName="block-library-video-tracks-editor"
        renderToggle={({ isOpen, onToggle }) => (
          <ToolbarGroup>
            <ToolbarButton
              label={__("Captions", "presto-player")}
              showTooltip
              aria-expanded={isOpen}
              aria-haspopup="true"
              onClick={onToggle}
              icon={captionIcon}
            />
          </ToolbarGroup>
        )}
        renderContent={({ onClose: closeDropdown }) => {
          dropdownOnCloseRef.current = closeDropdown;
          if (trackBeingEdited !== null && tracks[trackBeingEdited]) {
            const currentTrack = tracks[trackBeingEdited];
            return (
              <SingleTrackEditor
                track={currentTrack}
                onChange={(newTrack) => {
                  const newTracks = [...tracks];
                  newTracks[trackBeingEdited] = newTrack;
                  onChange(newTracks);
                }}
                onClose={() => setTrackBeingEdited(null)}
                onRemove={() => {
                  handleTrackRemove(trackBeingEdited);
                }}
                isBunnyNet={isBunnyNetCaption(currentTrack)}
              />
            );
          }
          return (
            <>
              <NavigableMenu>
                <TrackList tracks={tracks} onEditPress={setTrackBeingEdited} />
                <MenuGroup
                  className="block-library-video-tracks-editor__add-tracks-container"
                  label={__("Add caption languages", "presto-player")}
                >
                  <MediaUpload
                    onSelect={({ url }) => {
                      const trackIndex = tracks.length;
                      onChange([...tracks, { src: url }]);
                      setTrackBeingEdited(trackIndex);
                    }}
                    allowedTypes={ALLOWED_TYPES}
                    render={({ open }) => (
                      <MenuItem icon={"media"} onClick={open}>
                        {__("Open Media Library", "presto-player")}
                      </MenuItem>
                    )}
                  />
                  <MediaUploadCheck>
                    <FormFileUpload
                      onChange={(event) => {
                        const files = event.target.files;
                        const trackIndex = tracks.length;
                        mediaUpload({
                          allowedTypes: ALLOWED_TYPES,
                          filesList: files,
                          onFileChange: ([{ url }]) => {
                            const newTracks = [...tracks];
                            if (!newTracks[trackIndex]) {
                              newTracks[trackIndex] = {};
                            }
                            newTracks[trackIndex] = {
                              ...tracks[trackIndex],
                              src: url,
                            };
                            onChange(newTracks);
                            setTrackBeingEdited(trackIndex);
                          },
                        });
                      }}
                      accept=".vtt,text/vtt"
                      render={({ openFileDialog }) => {
                        return (
                          <MenuItem
                            icon={"upload"}
                            onClick={() => {
                              openFileDialog();
                            }}
                          >
                            {__("Upload", "presto-player")}
                          </MenuItem>
                        );
                      }}
                    />
                  </MediaUploadCheck>
                </MenuGroup>

                {/* Automatic Caption Settings - Only show for Bunny.net videos */}
                {onTranscribeLanguagesChange && (
                  <MenuGroup
                    className="block-library-video-tracks-editor__transcription-container"
                    label={__("Automatic Captions", "presto-player")}
                    css={css`
                      .components-menu-group__label {
                        padding: 0;
                      }
                    `}
                  >
                    <div style={{ padding: "6px 12px" }}>
                      <TranscriptionLanguageSelect
                        value={transcribeLanguages}
                        onChange={onTranscribeLanguagesChange}
                        showWarning={true}
                      />
                      <div
                        style={{
                          marginTop: "12px",
                          display: "flex",
                          gap: "8px",
                        }}
                      >
                        <Button
                          isPrimary
                          disabled={
                            !transcribeLanguages ||
                            transcribeLanguages.length === 0 ||
                            isTranscribing ||
                            !videoId
                          }
                          isBusy={isTranscribing}
                          onClick={() => {
                            if (!videoId || !transcribeLanguages.length) {
                              return;
                            }
                            setShowTranscribeConfirm(true);
                          }}
                        >
                          {__("Generate", "presto-player")}
                        </Button>
                      </div>
                    </div>
                  </MenuGroup>
                )}
              </NavigableMenu>
            </>
          );
        }}
      />
    </>
  );
}
