/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import { registerBlockVariation } from "@wordpress/blocks";
import { image as icon } from "@wordpress/icons";

/**
 * Register the popup cover trigger variation for the core cover block
 */
registerBlockVariation("core/cover", {
  icon: icon,
  name: "presto-player/popup-cover-trigger",
  title: __("Image", "presto-player"),
  description: __("Opens the popup when image is clicked.", "presto-player"),
  scope: [], // we leave it empty so it can only be inserted programmatically.
  attributes: {
    className: "presto-popup-cover-trigger",
  },
  isActive: ["className"],
});
