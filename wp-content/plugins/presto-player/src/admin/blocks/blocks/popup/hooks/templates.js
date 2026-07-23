import { __ } from "@wordpress/i18n";

/**
 * Image trigger template
 * @param {string} url - The URL of the image
 * @returns {Array} - The image trigger template
 */
export const imageTrigger = (url) => [
  "presto-player/popup-trigger",
  {},
  [image(url)],
];

/**
 * Image template
 * @param {string} url - The URL of the image
 * @returns {Array} - The image template
 */
export const image = (url) =>
  !prestoPlayer?.hasRequiredProVersion?.popups // In free version, we use popup-image-trigger variation.
    ? [
        "core/image",
        {
          url,
          className: "presto-popup-image-trigger",
          style: {
            border: {
              radius: "8px",
            },
          },
        },
      ]
    : [
        "core/cover",
        {
          url,
          className: "presto-popup-cover-trigger",
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
        },
        [
          [
            "core/group",
            {
              layout: {
                type: "flex",
                flexWrap: "nowrap",
                justifyContent: "center",
              },
            },
            [
              [
                "core/image",
                {
                  url: `${prestoPlayer?.plugin_url || ""}img/play-button.svg`,
                  alt: "",
                  width: "48px",
                  height: "auto",
                  sizeSlug: "large",
                  metadata: {
                    name: "Play Button",
                  },
                  align: "center",
                  className: "is-style-default",
                  style: {
                    color: {
                      duotone: "unset",
                    },
                    spacing: {
                      margin: {
                        top: "0",
                        right: "0",
                        bottom: "0",
                        left: "0",
                      },
                    },
                  },
                },
              ],
            ],
          ],
        ],
      ];

/**
 * Button trigger template
 * @param {string} text - The text of the button
 * @returns {Array} - The button trigger template
 */
export const buttonTrigger = (text = __("Play Video", "presto-player")) => [
  "presto-player/popup-trigger",
  {},
  [["core/buttons", {}, [button(text)]]],
];

/**
 * Button template
 * @param {string} text - The text of the button
 * @returns {Array} - The button template
 */
export const button = (text = __("Play Video", "presto-player")) => [
  "core/button",
  {
    text,
    prestoPopupTrigger: true,
  },
];

/**
 * Custom trigger template
 * @param {string} text - The text of the custom trigger
 * @returns {Array} - The custom trigger template
 */
export const customTrigger = (
  text = __(
    "Type / to choose a block to be the popup trigger...",
    "presto-player"
  )
) => ["presto-player/popup-trigger", {}, [custom(text)]];

/**
 * Custom template
 * @param {string} text - The text of the custom trigger
 * @returns {Array} - The custom template
 */
export const custom = (
  text = __(
    "Type / to choose a block to be the popup trigger...",
    "presto-player"
  )
) => [
  "core/paragraph",
  {
    content: "",
    placeholder: text,
  },
];
