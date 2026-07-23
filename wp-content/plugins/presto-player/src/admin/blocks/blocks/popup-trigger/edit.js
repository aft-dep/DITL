import { useBlockProps, useInnerBlocksProps } from "@wordpress/block-editor";
import { useSelect, useDispatch } from "@wordpress/data";
import { Placeholder } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { button as buttonIcon } from "@wordpress/icons";
import { css } from "@emotion/react";
import Tag from "../../shared/components/Tag";
import TriggerSelection from "../popup/components/TriggerSelection";
import { usePopupTemplate } from "../popup/hooks/usePopupTemplate";

export default ({ clientId }) => {
  // ================================================
  // Get context and block data
  // ================================================

  const { innerBlocks } = useSelect(
    (select) => ({
      innerBlocks: select("core/block-editor").getBlock(clientId).innerBlocks,
    }),
    [clientId]
  );

  const { replaceTemplate } = usePopupTemplate(clientId);

  const { setProModal } = useDispatch("presto-player/player");

  // ================================================
  // State checks
  // ================================================

  const hasInnerBlocks = innerBlocks && innerBlocks.length > 0;

  const blockProps = useBlockProps({
    css: css`
      position: relative;
    `,
  });

  // ================================================
  // Configure inner blocks
  // ================================================

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    // Allow different blocks based on premium status - use core/image instead of core/cover for non-premium users.
    allowedBlocks: !prestoPlayer?.hasRequiredProVersion?.popups
      ? ["core/image", "core/buttons", "core/button", "core/paragraph"]
      : true,
  });

  // ================================================
  // Render inner blocks
  // ================================================

  if (!hasInnerBlocks) {
    return (
      <Placeholder
        icon={buttonIcon}
        label={__("Popup Trigger", "presto-player")}
        instructions={__(
          "Select a trigger type for your video popup.",
          "presto-player"
        )}
        {...blockProps}
      >
        <TriggerSelection
          onTriggerTypeSelect={(type) => {
            if (
              type === "custom" &&
              !prestoPlayer?.hasRequiredProVersion?.popups
            ) {
              setProModal(true);
              return;
            }
            // Only replace the specific inner blocks with the selected type.
            // Reference the templates.js file for the available types.
            replaceTemplate({ type });
          }}
        />
      </Placeholder>
    );
  }

  return (
    <div style={{ position: "relative" }}>
      <Tag
        label={__("Trigger", "presto-player")}
        className="presto-popup-tag"
        css={css`
          position: absolute;
          top: 0px;
          right: 0px;
          z-index: 10;
          display: none;
          border-radius: 1px;
        `}
      />
      <div {...innerBlocksProps} />
    </div>
  );
};
