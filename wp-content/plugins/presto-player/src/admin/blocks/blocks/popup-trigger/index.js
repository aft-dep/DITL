/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import { button as icon } from "@wordpress/icons";

/**
 * Internal dependencies
 */
import edit from "./edit";
import save from "./save";
import metadata from "./block.json";
const { name } = metadata;

export { metadata, name };

export const options = {
  usesContext: ["presto-player/popup-trigger-type"],
  icon,
  edit,
  save,
};
