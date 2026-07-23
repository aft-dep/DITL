import { useBlockProps, useInnerBlocksProps } from "@wordpress/block-editor";
import { css } from "@emotion/react";
import Context from "../popup/context/context";
import Tag from "../../shared/components/Tag";
import { __ } from "@wordpress/i18n";

export default ({ clientId }) => {
  const blockProps = useBlockProps({
    className: "presto-popup__overlay",
    css: css`
      position: relative;
    `,
  });

  const innerBlocksProps = useInnerBlocksProps({
    className: "presto-popup__content",
  });

  return (
    <Context.Consumer>
      {({ open }) => {
        if (!open) {
          return null;
        }

        return (
          <div style={{ position: "relative" }}>
            <Tag
              label={__("Popup", "presto-player")}
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
            <div {...blockProps}>
              <button
                type="button"
                aria-label="Close"
                className="presto-popup__close-button"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  width="24"
                  height="24"
                  aria-hidden="true"
                  focusable="false"
                  fill="white"
                >
                  <path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z" />
                </svg>
              </button>
              <div {...innerBlocksProps} />
            </div>
          </div>
        );
      }}
    </Context.Consumer>
  );
};
