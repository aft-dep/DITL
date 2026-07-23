import * as template from "./templates";
import { useDispatch } from "@wordpress/data";
import { createBlocksFromInnerBlocksTemplate } from "@wordpress/blocks";

/**
 * Hook for creating popup templates
 *
 * @returns {Object} { replaceTemplate }
 */
export const usePopupTemplate = (clientId) => {
  const { replaceInnerBlocks } = useDispatch("core/block-editor");

  /**
   * Replaces specific template parts based on available params
   * Performs the actual replaceInnerBlocks operation
   */
  const replaceTemplate = ({
    type,
    provider,
    mediaId = null,
    imageUrl = null,
  }) => {
    if (!clientId) {
      console.warn("replaceTemplate: clientId is required");
      return;
    }

    // Initialize the templates.
    let mediaTemplate = [];
    let templateToUse = [];

    // Set the appropriate static template as base template from templates.js.
    let triggerTemplate = imageUrl
      ? template[type](imageUrl)
      : template[type]();

    if (!triggerTemplate) {
      console.warn(`replaceTemplate: template for type "${type}" not found`);
      return;
    }

    // Add the media block to the template.
    if (provider || mediaId) {
      mediaTemplate = [
        "presto-player/popup-media",
        {},
        [
          mediaId
            ? ["presto-player/reusable-display", { id: mediaId }]
            : [`presto-player/${provider}`, {}],
        ],
      ];
    }

    // This is to ensure empty arrays are not passed to template.
    if (triggerTemplate.length > 0) {
      templateToUse = [triggerTemplate];
    }

    if (mediaTemplate.length > 0) {
      templateToUse = [...templateToUse, mediaTemplate];
    }

    // Replace the inner blocks with the template.
    replaceInnerBlocks(
      clientId,
      createBlocksFromInnerBlocksTemplate(templateToUse)
    );
  };

  return {
    replaceTemplate,
  };
};
