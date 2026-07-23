import { addFilter } from "@wordpress/hooks";
import { createHigherOrderComponent } from "@wordpress/compose";
import Context from "../blocks/popup/context/context";
import PreviewToggle from "../blocks/popup/components/PreviewToggle";

/**
 * Higher order component to add tab switcher to blocks in popup context
 */
const WithPopupPreviewToggle = createHigherOrderComponent((BlockEdit) => {
  return (props) => (
    <Context.Consumer>
      {(contextValue) => {
        const { open, setOpen } = contextValue || {};
        return (
          <>
            {contextValue && <PreviewToggle open={open} setOpen={setOpen} />}
            <BlockEdit {...props} />
          </>
        );
      }}
    </Context.Consumer>
  );
}, "withPopupPreviewToggle");

/**
 * Custom hook to initialize the popup tab switcher
 */
// Apply the filter to all block edits
addFilter(
  "editor.BlockEdit",
  "presto-player/popup-preview-toggle",
  WithPopupPreviewToggle
);
