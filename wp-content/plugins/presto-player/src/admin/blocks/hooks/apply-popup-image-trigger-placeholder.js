import { addFilter } from "@wordpress/hooks";
import { createHigherOrderComponent } from "@wordpress/compose";
import { MediaUpload, MediaUploadCheck } from "@wordpress/block-editor";
import { Button, Placeholder } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { usePopupTemplate } from "../blocks/popup/hooks/usePopupTemplate";

/**
 * Higher order component to add image placeholder for popup-image-trigger block variation
 */
const WithPopupImageTriggerPlaceholder = createHigherOrderComponent(
  (BlockEdit) => {
    return (props) => {
      const { name, attributes, setAttributes } = props;

      // Return if block type is not cover or image.
      if (name !== "core/cover" && name !== "core/image") {
        return <BlockEdit {...props} />;
      }

      // Return if block doesn't have required class names.
      if (
        attributes?.className !== "presto-popup-image-trigger" &&
        attributes?.className !== "presto-popup-cover-trigger"
      ) {
        return <BlockEdit {...props} />;
      }

      // Return if image/cover already has URL
      if (attributes?.url) {
        return <BlockEdit {...props} />;
      }

      /**
       * Handle the image upload.
       * @param {Object} media - The uploaded media object
       * @returns {void}
       */
      const handleImageUpload = (media) => {
        if (!media?.url) {
          return;
        }

        if (name === "core/cover") {
          // Update the cover block attributes with the image url.
          setAttributes({
            url: media?.url,
            id: media?.id,
            customOverlayColor: "#131313",
            isUserOverlayColor: true,
            dimRatio: 50,
            minHeight: 336,
            minHeightUnit: "px",
            contentPosition: "center center",
            layout: { type: "constrained" },
            style: {
              aspectRatio: "16/9",
              border: {
                radius: "5px",
              },
            },
          });
        } else if (name === "core/image") {
          // Update the image block attributes with the image url.
          setAttributes({
            url: media?.url,
            id: media?.id,
            alt: media?.alt || "",
            style: {
              border: {
                radius: "8px",
              },
            },
          });
        }
      };

      return (
        <Placeholder
          icon="format-image"
          label={__("Choose media", "presto-player")}
          instructions={__(
            "Select an image that will trigger the popup when clicked.",
            "presto-player"
          )}
        >
          <MediaUploadCheck>
            <MediaUpload
              onSelect={handleImageUpload}
              allowedTypes={["image"]}
              render={({ open }) => (
                <Button onClick={open} variant="primary">
                  {__("Add / Select Image", "presto-player")}
                </Button>
              )}
            />
          </MediaUploadCheck>
        </Placeholder>
      );
    };
  },
  "withPopupImageTriggerPlaceholder"
);

/**
 * Apply the filter to popup-image-trigger block variation for image placeholder
 */
addFilter(
  "editor.BlockEdit",
  "presto-player/popup-image-trigger-placeholder",
  WithPopupImageTriggerPlaceholder
);
