/** @jsx jsx */
import { css, jsx } from "@emotion/core";
import {
  store as blockEditorStore,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";
import { select, useSelect, useDispatch } from "@wordpress/data";
import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import MediaProviders from "../../shared/MediaProviders";
import { useContext } from "@wordpress/element";
import EditContext from "../reusable-display/context";
import { createBlock } from "@wordpress/blocks";

export default ({ clientId, isSelected, context }) => {
  const { selectBlock, insertBlock } = useDispatch(blockEditorStore);
  const { setTemplateValidity } = useDispatch(blockEditorStore);
  const { isEditing } = useContext(EditContext);
  const innerBlocks = useSelect(
    (select) => select(blockEditorStore).getBlock(clientId).innerBlocks
  );

  const blockProps = useBlockProps();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    templateLock: isEditing ? "all" : false, // lock the template if we are in the editing context.
    renderAppender: false,
  });

  setTemplateValidity(true);

  useEffect(() => {
    // if this is selected, and we are in the playlist context, select the inner block.
    if (isSelected && context["presto-player/playlist-media-id"]) {
      const blockOrder = select(blockEditorStore).getBlockOrder(clientId);
      const firstInnerBlockClientId = blockOrder[0];
      if (firstInnerBlockClientId) {
        selectBlock(firstInnerBlockClientId);
      }
    }
  }, [isSelected]);

  if (!innerBlocks?.length) {
    return (
      <div {...blockProps}>
        <MediaProviders
          sync={false}
          onSelect={(type) => {
            insertBlock(createBlock(`presto-player/${type}`), 0, clientId);
          }}
          onSelectMedia={false}
        />
        <div {...innerBlocksProps} />
      </div>
    );
  }

  return <div {...innerBlocksProps} />;
};
