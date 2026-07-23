/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";

/**
 * Internal dependencies
 */
import edit from "./edit";
import save from "./save";
import metadata from "./block.json";
import { aspectRatio as icon } from "@wordpress/icons";
const { name } = metadata;

export { metadata, name };

export const options = {
  icon,
  edit,
  save,
};
