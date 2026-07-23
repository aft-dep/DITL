/** @jsx jsx */
import { css, jsx } from "@emotion/react";
import {
  useBlockProps,
  store as blockEditorStore,
  BlockControls,
  useInnerBlocksProps,
} from "@wordpress/block-editor";
import { useState, useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { button } from "@wordpress/icons";
import {
  Placeholder,
  MenuGroup,
  MenuItem,
  ToolbarDropdownMenu,
} from "@wordpress/components";
import { useSelect, useDispatch, select } from "@wordpress/data";
import { useEntityProp, store as coreStore } from "@wordpress/core-data";
import { symbol, symbolFilled } from "@wordpress/icons";
import MediaProviders from "../../shared/MediaProviders";
import Context from "./context/context";
import TriggerSelection from "./components/TriggerSelection";
import { usePopupTemplate } from "./hooks/usePopupTemplate";

function Edit({ clientId, attributes, setAttributes }) {
  const [triggerType, setTriggerType] = useState("");
  const [open, setOpen] = useState(true);
  const { setProModal } = useDispatch("presto-player/player");
  const blockProps = useBlockProps();
  const { replaceTemplate } = usePopupTemplate(clientId);

  // Set a unique ID for the popup using the block's clientId
  useEffect(() => {
    if (!attributes?.popupId) {
      setAttributes({ popupId: clientId });
    }
  }, [attributes?.popupId, clientId, setAttributes]);

  const [mediaHubSyncDefault] = useEntityProp(
    "root",
    "site",
    "presto_player_media_hub_sync_default"
  );
  const [sync, setSync] = useState(() => mediaHubSyncDefault);

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ["presto-player/popup-trigger", "presto-player/popup-media"],
    template: [["presto-player/popup-trigger"], ["presto-player/popup-media"]],
  });

  /**
   * Get inner blocks
   */
  const hasInnerBlocks = useSelect(
    (select) =>
      select(blockEditorStore).getBlock(clientId).innerBlocks?.length > 0
  );

  /**
   * Handle trigger type selection with pro check
   */
  const handleTriggerTypeSelect = (type) => {
    if (type === "custom" && !prestoPlayer?.hasRequiredProVersion?.popups) {
      setProModal(true);
      return;
    }
    setTriggerType(type);
  };

  // Handler for when existing media is selected
  const handleMediaSelected = (mediaId) => {
    // Get media item
    const queryArgs = ["postType", "pp_video_block", mediaId];
    const media = select(coreStore).getEntityRecord(...queryArgs);

    if (!triggerType) {
      return;
    }

    // Get poster image if it's an image trigger
    const imageUrl =
      media?.details?.poster && triggerType === "image"
        ? media?.details?.poster
        : null;

    // Replace inner blocks with template
    replaceTemplate({
      type: `${triggerType}Trigger`,
      mediaId,
      imageUrl,
    });
  };

  // ================================================
  // If there are inner blocks already.
  // ================================================
  if (hasInnerBlocks) {
    return (
      <>
        {/*
         * Context provider for all blocks nested within the popup trigger
         * This is used to provide the popup open/closed tab to all blocks
         * nested within the popup trigger.
         */}
        <Context.Provider value={{ open, setOpen }}>
          <div {...innerBlocksProps}></div>
        </Context.Provider>
      </>
    );
  }

  // ================================================
  // Select trigger type
  // ================================================
  if (!triggerType) {
    return (
      <Placeholder
        icon={button}
        label={__("Popup Trigger", "presto-player")}
        instructions={__(
          "Select a trigger type for your video popup.",
          "presto-player"
        )}
        {...blockProps}
      >
        <TriggerSelection
          triggerType={triggerType}
          onTriggerTypeSelect={handleTriggerTypeSelect}
        />
      </Placeholder>
    );
  }

  // ================================================
  // Media selection step with sync controls
  // ================================================
  return (
    <div {...blockProps}>
      <BlockControls group="other">
        <ToolbarDropdownMenu
          label={__("Media Hub Sync", "presto-player")}
          icon={sync ? symbolFilled : symbol}
          text={
            sync
              ? __("Synced", "presto-player")
              : __("Not synced", "presto-player")
          }
        >
          {({ onClose }) => (
            <MenuGroup>
              <MenuItem
                onClick={() => {
                  setSync(true);
                  onClose();
                }}
                icon={sync ? "yes" : undefined}
                isSelected={sync}
              >
                {__("Sync to media hub", "presto-player")}
              </MenuItem>
              <MenuItem
                onClick={() => {
                  setSync(false);
                  onClose();
                }}
                icon={!sync ? "yes" : undefined}
                isSelected={!sync}
              >
                {__("Don't sync to media hub", "presto-player")}
              </MenuItem>
            </MenuGroup>
          )}
        </ToolbarDropdownMenu>
      </BlockControls>

      <MediaProviders
        sync={sync}
        onSyncedMediaCreated={(id) => {
          // Once synced media is created, replace the template with the new media.
          replaceTemplate({ type: `${triggerType}Trigger`, mediaId: id });
        }}
        onSelect={(provider) => {
          // Once a provider is selected, replace the template with the new provider.
          replaceTemplate({ type: `${triggerType}Trigger`, provider });
        }}
        onSelectMedia={handleMediaSelected}
      />
    </div>
  );
}

export default Edit;
