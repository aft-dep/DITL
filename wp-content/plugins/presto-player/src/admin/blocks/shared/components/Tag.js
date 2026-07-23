/** @jsx jsx */
import { css, jsx } from "@emotion/core";

export default ({ label, className, ...props }) => {
  return (
    <span
      className={className}
      css={css`
        background: var(--wp-admin-theme-color);
        color: white;
        font-size: 10px;
        font-weight: 500;
        padding: 2px 6px;
        pointer-events: none;
        letter-spacing: 0.3px;
        line-height: 1.3;
        border-radius-bottom-left: 2px;
        border: none;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
          Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        display: inline-block;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
      `}
      {...props}
    >
      {label}
    </span>
  );
};
