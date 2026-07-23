/** @jsx jsx */
import { css, jsx } from "@emotion/core";
import {
  store as blockEditorStore,
  useBlockProps,
  useInnerBlocksProps,
  BlockControls,
} from "@wordpress/block-editor";
import { useSelect, useDispatch } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import {
  Flex,
  Toolbar,
  Button,
  Dropdown,
  MenuItem,
  Icon,
  MenuGroup,
} from "@wordpress/components";
import { symbol, symbolFilled } from "@wordpress/icons";
import { useState } from "@wordpress/element";
import { useEntityProp } from "@wordpress/core-data";
import { createBlock } from "@wordpress/blocks";
import { store as coreStore } from "@wordpress/core-data";
import MediaProviders from "../../shared/MediaProviders";

export default ({ clientId, attributes, setAttributes, context }) => {
  const { setTemplateValidity } = useDispatch(blockEditorStore);
  const { replaceBlock } = useDispatch(blockEditorStore);
  const { saveEntityRecord } = useDispatch(coreStore);
  const { createErrorNotice } = useDispatch("core/notices");
  const innerBlocks = useSelect(
    (select) => select(blockEditorStore).getBlock(clientId).innerBlocks
  );
  setTemplateValidity(true);
  const blockProps = useBlockProps();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    templateLock: false,
    renderAppender: false,
  });
  const [mediaHubSyncDefault] = useEntityProp(
    "root",
    "site",
    "presto_player_media_hub_sync_default"
  );
  const [sync, setSync] = useState(() => mediaHubSyncDefault);
  const [saving, setSaving] = useState(false);

  // Create a video with media hub sync
  const createSyncedVideo = async (videoType) => {
    if (saving) return;
    try {
      setSaving(true);
      const { id } = await saveEntityRecord(
        "postType",
        "pp_video_block",
        {
          status: "publish",
          content: `<!-- wp:presto-player/reusable-edit -->
            <div class="wp-block-presto-player-reusable-edit"><!-- wp:presto-player/${videoType} /--></div>
            <!-- /wp:presto-player/reusable-edit -->`,
        },
        { throwOnError: true }
      );

      replaceBlock(
        clientId,
        createBlock("presto-player/reusable-display", {
          id,
        })
      );
    } catch (e) {
      createErrorNotice(
        e?.message || __("Something went wrong", "presto-player")
      );
    } finally {
      setSaving(false);
    }
  };

  // Handle media selection
  const handleMediaSelected = (id) => {
    replaceBlock(
      clientId,
      createBlock("presto-player/reusable-display", {
        id,
      })
    );
  };

  if (!innerBlocks?.length) {
    return (
      <div {...innerBlocksProps}>
        {
          <>
            <BlockControls>
              <Toolbar>
                <Dropdown
                  popoverProps={{ placement: "bottom-left" }}
                  renderToggle={({ onToggle }) => (
                    <Flex>
                      <Button
                        onClick={onToggle}
                        css={css`
                          background: transparent;
                          border: none;
                          cursor: pointer;
                          display: flex;
                          justify-content: space-between;
                          align-items: center;
                          gap: 4px;
                        `}
                        icon={
                          sync ? (
                            <Icon icon={symbolFilled} />
                          ) : (
                            <Icon icon={symbol} />
                          )
                        }
                      >
                        {sync
                          ? __("Synced", "presto-player")
                          : __("Not synced", "presto-player")}
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 16 16"
                          fill="currentColor"
                          className="w-4 h-4"
                          width={"16px"}
                        >
                          <path
                            fillRule="evenodd"
                            d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                            clipRule="evenodd"
                          />
                        </svg>
                      </Button>
                    </Flex>
                  )}
                  renderContent={() => (
                    <MenuGroup>
                      <MenuItem
                        onClick={() => setSync(true)}
                        icon={<Icon icon={symbolFilled} />}
                        isSelected={sync}
                        iconPosition="left"
                      >
                        {__("Sync to media hub", "presto-player")}
                      </MenuItem>
                      <MenuItem
                        onClick={() => setSync(false)}
                        icon={<Icon icon={symbol} />}
                        isSelected={!sync}
                        iconPosition="left"
                      >
                        {__("Don't sync to media hub", "presto-player")}
                      </MenuItem>
                    </MenuGroup>
                  )}
                />
              </Toolbar>
            </BlockControls>

            <MediaProviders
              sync={sync}
              onSyncedMediaCreated={(id) => {
                replaceBlock(
                  clientId,
                  createBlock("presto-player/reusable-display", {
                    id,
                  })
                );
              }}
              onSelect={(type) => {
                replaceBlock(clientId, createBlock(`presto-player/${type}`));
              }}
              onSelectMedia={handleMediaSelected}
            />
          </>
        }
      </div>
    );
  }
  return <div {...innerBlocksProps}></div>;
};
