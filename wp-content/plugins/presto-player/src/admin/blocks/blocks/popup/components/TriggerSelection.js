/** @jsx jsx */
import { css, jsx } from "@emotion/core";
import { __ } from "@wordpress/i18n";
import ProBadge from "../../../shared/components/ProBadge";

const TriggerSelection = ({ triggerType, onTriggerTypeSelect }) => {
  return (
    <div
      css={css`
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 16px;
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
      `}
    >
      <div
        css={css`
          border: 1px solid #e2e4e7;
          border-radius: 4px;
          padding: 16px;
          text-align: center;
          cursor: pointer;
          transition: all 0.2s;
          position: relative;
          ${triggerType === "image" &&
          "background-color: #f0f6fc; border-color: #007cba;"}
          &:hover {
            border-color: #007cba;
            box-shadow: 0 0 0 1px #007cba;
          }
        `}
        onClick={() => onTriggerTypeSelect("image")}
      >
        <div
          css={css`
            background: #f1f1f1;
            width: 48px;
            height: 48px;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
          `}
        >
          <span className="dashicons dashicons-format-image"></span>
        </div>
        <div
          css={css`
            font-weight: 500;
          `}
        >
          {__("Image", "presto-player")}
        </div>
        <div
          css={css`
            font-size: 12px;
            color: #757575;
            margin-top: 4px;
          `}
        >
          {__("Image thumbnail trigger", "presto-player")}
        </div>
      </div>

      <div
        css={css`
          border: 1px solid #e2e4e7;
          border-radius: 4px;
          padding: 16px;
          text-align: center;
          cursor: pointer;
          transition: all 0.2s;
          position: relative;
          ${triggerType === "button" &&
          "background-color: #f0f6fc; border-color: #007cba;"}
          &:hover {
            border-color: #007cba;
            box-shadow: 0 0 0 1px #007cba;
          }
        `}
        onClick={() => onTriggerTypeSelect("button")}
      >
        <div
          css={css`
            background: #f1f1f1;
            width: 48px;
            height: 48px;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
          `}
        >
          <span className="dashicons dashicons-button"></span>
        </div>
        <div
          css={css`
            font-weight: 500;
          `}
        >
          {__("Button", "presto-player")}
        </div>
        <div
          css={css`
            font-size: 12px;
            color: #757575;
            margin-top: 4px;
          `}
        >
          {__("Simple button trigger", "presto-player")}
        </div>
      </div>

      <div
        css={css`
          border: 1px solid #e2e4e7;
          border-radius: 4px;
          padding: 16px;
          text-align: center;
          cursor: pointer;
          transition: all 0.2s;
          position: relative;
          ${triggerType === "custom" &&
          "background-color: #f0f6fc; border-color: #007cba;"}
          &:hover {
            border-color: #007cba;
            box-shadow: 0 0 0 1px #007cba;
          }
        `}
        onClick={() => onTriggerTypeSelect("custom")}
      >
        {!prestoPlayer?.hasRequiredProVersion?.popups && (
          <div
            css={css`
              position: absolute;
              top: 8px;
              right: 8px;
            `}
          >
            <ProBadge />
          </div>
        )}
        <div
          css={css`
            background: #f1f1f1;
            width: 48px;
            height: 48px;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
          `}
        >
          <span className="dashicons dashicons-edit"></span>
        </div>
        <div
          css={css`
            font-weight: 500;
          `}
        >
          {__("Custom", "presto-player")}
        </div>
        <div
          css={css`
            font-size: 12px;
            color: #757575;
            margin-top: 4px;
          `}
        >
          {__("Create your own trigger", "presto-player")}
        </div>
      </div>
    </div>
  );
};

export default TriggerSelection;
