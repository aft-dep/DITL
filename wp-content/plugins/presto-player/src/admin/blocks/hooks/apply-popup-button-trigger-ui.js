import { addFilter } from "@wordpress/hooks";
import { createHigherOrderComponent } from "@wordpress/compose";
import { BlockControls } from "@wordpress/block-editor";
import { ToolbarButton, ToolbarGroup } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { copySmall, closeSmall } from "@wordpress/icons";
import Context from "../blocks/popup/context/context";

/**
 * Higher order component to add popup trigger toggle to core/button blocks in popup context
 */
const WithPopupButtonTriggerUI = createHigherOrderComponent((BlockEdit) => {
  return (props) => {
    const {
      name,
      attributes: { prestoPopupTrigger },
      setAttributes,
    } = props;

    // Only apply to core/button blocks
    if (name !== "core/button") {
      return <BlockEdit {...props} />;
    }

    return (
      <Context.Consumer>
        {(contextValue) => {
          return (
            <>
              <BlockEdit {...props} />

              {/* Only show the UI if we're in a popup context */}
              {contextValue && (
                <BlockControls>
                  <ToolbarGroup>
                    <ToolbarButton
                      icon={prestoPopupTrigger ? closeSmall : copySmall}
                      onClick={() =>
                        setAttributes({
                          prestoPopupTrigger: !prestoPopupTrigger,
                        })
                      }
                      aria-pressed={prestoPopupTrigger}
                    >
                      {prestoPopupTrigger
                        ? __("Remove as Popup Trigger", "presto-player")
                        : __("Use as Popup Trigger", "presto-player")}
                    </ToolbarButton>
                  </ToolbarGroup>
                </BlockControls>
              )}
            </>
          );
        }}
      </Context.Consumer>
    );
  };
}, "withPopupButtonTriggerUI");

// Apply the filter to all block edits
addFilter(
  "editor.BlockEdit",
  "presto-player/popup-button-trigger-ui",
  WithPopupButtonTriggerUI
);
