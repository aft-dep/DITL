/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import { registerBlockVariation } from "@wordpress/blocks";
import { createHigherOrderComponent } from "@wordpress/compose";
import { edit } from "@wordpress/icons";
import { BlockControls } from "@wordpress/block-editor";
import { ToolbarButton, ToolbarGroup } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { createBlocksFromInnerBlocksTemplate } from "@wordpress/blocks";
import { addFilter } from "@wordpress/hooks";
import { image } from "../../blocks/popup/hooks/templates";

/**
 * Register the popup image trigger variation for the core image block
 */
registerBlockVariation("core/image", {
  name: "presto-player/popup-image-trigger",
  title: __("Image", "presto-player"),
  description: __("Opens the popup when image is clicked.", "presto-player"),
  scope: [], // we leave it empty so it can only be inserted programmatically.
  attributes: {
    className: "presto-popup-image-trigger",
  },
  isActive: ["className"],
});

const withProUpgrade = createHigherOrderComponent((BlockEdit) => {
  return (props) => {
    const { setProModal } = useDispatch("presto-player/player");
    const { getBlock } = useSelect("core/block-editor");
    const { replaceBlock } = useDispatch("core/block-editor");

    /**
     * @param {string} clientId - The block's client ID
     * @returns {void}
     */
    const transformToCover = (clientId) => {
      // Get the current block data
      const currentBlock = getBlock(clientId);
      if (!currentBlock) {
        console.warn(
          `transformToCover: Block with clientId ${clientId} not found`
        );
        return;
      }

      // Create the new block from the template
      const newBlocks = createBlocksFromInnerBlocksTemplate([
        image(currentBlock?.attributes?.url || currentBlock?.attributes?.src),
      ]);

      if (!newBlocks?.[0]) {
        console.warn("transformToCover: New blocks not found");
        return;
      }

      replaceBlock(clientId, newBlocks?.[0]);
    };

    if (props?.name !== "core/image") {
      return <BlockEdit {...props} />;
    }

    if (!props?.attributes?.className?.includes("presto-popup-image-trigger")) {
      return <BlockEdit {...props} />;
    }

    return (
      <>
        <BlockEdit {...props} />
        <BlockControls>
          <ToolbarGroup>
            <ToolbarButton
              icon={edit}
              label="Edit"
              onClick={() =>
                !prestoPlayer?.hasRequiredProVersion?.popups
                  ? setProModal(true)
                  : transformToCover(props?.clientId)
              }
            >
              {__("Edit Play Button", "presto-player")}
            </ToolbarButton>
          </ToolbarGroup>
        </BlockControls>
      </>
    );
  };
}, "withProUpgrade");

addFilter(
  "editor.BlockEdit",
  "presto-player/image-popup-trigger",
  withProUpgrade
);
