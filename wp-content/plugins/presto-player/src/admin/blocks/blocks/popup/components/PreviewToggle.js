import { __ } from "@wordpress/i18n";
import { BlockControls } from "@wordpress/block-editor";
import { ToolbarButton, ToolbarGroup } from "@wordpress/components";
import { aspectRatio } from "@wordpress/icons";

export default ({ open, setOpen }) => {
  return (
    <>
      <BlockControls>
        <ToolbarGroup>
          <ToolbarButton
            icon={aspectRatio}
            label={
              open
                ? __("Hide Popup", "presto-player")
                : __("Show Popup", "presto-player")
            }
            onClick={() => setOpen(!open)}
            aria-pressed={open}
          />
        </ToolbarGroup>
      </BlockControls>
    </>
  );
};
