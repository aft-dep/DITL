import {
  ExternalLink,
  TextControl,
  ToggleControl,
  Modal,
  Button,
} from "@wordpress/components";
import { useEntityProp } from "@wordpress/core-data";
import { Fragment, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

const semverCompare = require("semver/functions/compare");

import Group from "../components/Group";
import Page from "../components/Page";
import BunnyClassic from "./parts/BunnyClassic/index";
import BunnyStream from "./parts/BunnyStream/index";
import EmailExport from "./parts/EmailExport";
import CTA from "../components/CTA";

import ActiveCampaign from "./parts/integration/ActiveCampaign";
import FluentCRM from "./parts/integration/FluentCRM";
import Mailchimp from "./parts/integration/Mailchimp";
import MailerLite from "./parts/integration/MailerLite";
import Webhooks from "./parts/Webhooks/index.js";
import TranscriptionLanguageSelect from "@/admin/blocks/shared/components/TranscriptionLanguageSelect";

export default () => {
  const [editBunny, setEditBunny] = useState(false);
  const [showTranscribeConfirm, setShowTranscribeConfirm] = useState(false);

  const [stream, setStream] = useEntityProp(
    "root",
    "site",
    "presto_player_bunny_stream"
  );
  const updateStream = (data) => {
    setStream({
      ...(stream || {}),
      ...data,
    });
  };

  const [analytics, setAnalytics] = useEntityProp(
    "root",
    "site",
    "presto_player_google_analytics"
  );
  const updateAnalytics = (data) => {
    setAnalytics({
      ...(analytics || {}),
      ...data,
    });
  };

  const [youtube, setYoutube] = useEntityProp(
    "root",
    "site",
    "presto_player_youtube"
  );
  const updateYoutube = (data) => {
    setYoutube({
      ...(youtube || {}),
      ...data,
    });
  };

  const [usageTracking, setUsageTracking] = useEntityProp(
    "root",
    "site",
    "presto-player_usage_optin"
  );

  const disabled = () => {
    if (prestoPlayer?.isPremium) {
      return false;
    }
    return {
      title: __("Pro Feature", "presto-player"),
      heading: __("Unlock Presto Player Pro", "presto-player"),
      message: __(
        "Get this feature and more with the Pro version of Presto Player!",
        "presto-player"
      ),
      link: "https://prestoplayer.com",
    };
  };

  const showEmailIntegration = () => {
    if (!prestoPlayer?.isPremium) {
      return true;
    }

    if (
      prestoPlayer?.proVersion &&
      semverCompare(prestoPlayer?.proVersion, "0.9.0") >= 0
    ) {
      return true;
    }

    return false;
  };

  // Check if transcription settings are valid (enabled requires at least one language)
  const isTranscriptionInvalid =
    stream?.transcribe_enabled &&
    (!stream?.transcribe_languages ||
      stream?.transcribe_languages.length === 0);

  const bunnySettings = () => {
    return (
      <>
        <Group
          title={__("Bunny.net Settings", "presto-player")}
          description={__("Modify bunny.net settings.", "presto-player")}
          saveDisabled={isTranscriptionInvalid}
        >
          {!!window?.prestoPlayer?.isSetup?.bunny?.stream && (
            <Fragment>
              <TextControl
                label={__("Initial Quality Level", "presto-player")}
                help={__(
                  "You can set the default quality start level for all streams (i.e. 240, 360, 480, 720, 1080, etc). Set this lower to prevent initial buffering if your users have slow connections. Set this higher to start with a higher quality stream.",
                  "presto-player"
                )}
                placeholder={"480"}
                type="number"
                value={stream?.hls_start_level}
                onChange={(hls_start_level) =>
                  updateStream({ hls_start_level })
                }
              />

              {<br />}

              <ToggleControl
                label={__("Disable Classic Bunny.net Storage", "presto-player")}
                help={__(
                  "This will disable Bunny.net classic storage in your block UI.",
                  "presto-player"
                )}
                checked={stream?.disable_legacy_storage}
                onChange={(disable_legacy_storage) =>
                  updateStream({ disable_legacy_storage })
                }
              />
            </Fragment>
          )}

          <ToggleControl
            label={__("Edit Bunny.net Settings", "presto-player")}
            help={__(
              "Edit Bunny.net connection settings. Only edit this if you know what you're doing.",
              "presto-player"
            )}
            checked={editBunny}
            onChange={setEditBunny}
          />

          {!!editBunny && (
            <>
              <BunnyStream />
              <BunnyClassic />
            </>
          )}
          <>
            {<br />}

            <h3>{__("Automatic Captions", "presto-player")}</h3>
            <p
              style={{
                fontSize: "12px",
                color: "#757575",
                marginBottom: "16px",
              }}
            >
              {__(
                "Detects speech and generates captions for new videos. Includes the option to translate into 50+ languages.",
                "presto-player"
              )}
            </p>

            {<br />}

            <ToggleControl
              label={__("Enable Automatic Caption Generation", "presto-player")}
              help={__(
                "Enable auto-captioning for every video uploaded to your Bunny.net Stream Library. Captions publish automatically and are visible on the player once processing is completed. Translations can be managed at any time. Simply select the CC icon in the player block toolbar within the editor to generate additional languages.",
                "presto-player"
              )}
              checked={stream?.transcribe_enabled}
              onChange={(transcribe_enabled) => {
                if (transcribe_enabled) {
                  setShowTranscribeConfirm(true);
                } else {
                  updateStream({ transcribe_enabled });
                }
              }}
            />

            {<br />}
            {stream?.transcribe_enabled && (
              <TranscriptionLanguageSelect
                value={stream?.transcribe_languages || []}
                onChange={(transcribe_languages) =>
                  updateStream({ transcribe_languages })
                }
                showWarning={true}
              />
            )}
          </>
        </Group>
      </>
    );
  };

  return (
    <>
      {showTranscribeConfirm && (
        <Modal
          title={__("Caption Generation Charges", "presto-player")}
          onRequestClose={() => setShowTranscribeConfirm(false)}
          style={{ maxWidth: "450px" }}
        >
          <p>
            {__(
              "This will automatically generate captions for every video you upload, unlocking automatic captions and translations. You will be charged $0.10 per minute for each language from your Bunny.net account balance.",
              "presto-player"
            )}
          </p>
          <p style={{ marginTop: "8px" }}>
            <a
              href="https://docs.bunny.net/docs/stream-pricing#transcribing"
              target="_blank"
              rel="noopener noreferrer"
              style={{
                color: "var(--wp-admin-theme-color, #007cba)",
                textDecoration: "underline",
              }}
            >
              {__("View Bunny.net pricing details", "presto-player")} →
            </a>
          </p>
          <div
            style={{
              display: "flex",
              justifyContent: "flex-end",
              gap: "8px",
              marginTop: "16px",
            }}
          >
            <Button
              variant="secondary"
              onClick={() => setShowTranscribeConfirm(false)}
            >
              {__("Cancel", "presto-player")}
            </Button>
            <Button
              variant="primary"
              onClick={() => {
                updateStream({ transcribe_enabled: true });
                setShowTranscribeConfirm(false);
              }}
            >
              {__("Enable", "presto-player")}
            </Button>
          </div>
        </Modal>
      )}
      <Page
        title={__("Integrations", "presto-player")}
        description={__(
          "Third party integrations and connections.",
          "presto-player"
        )}
      >
        <Group
          title={__("Google Analytics", "presto-player")}
          description={__(
            "Analytics settings for media plays, watch times and more.",
            "presto-player"
          )}
          disabled={disabled()}
        >
          <ToggleControl
            className="presto-player__setting--google-analytics"
            label={__("Enable", "presto-player")}
            help={__(
              "Send analytics events to your Google Analytics account.",
              "presto-player"
            )}
            checked={analytics?.enable}
            onChange={(enable) => updateAnalytics({ enable })}
          />

          <ToggleControl
            className="presto-player__setting--use-existing-tag"
            label={__("Use existing on-page tag?", "presto-player")}
            help={__(
              "Should we use an existing google analytics (v4) tag? If not, we'll output one for you.",
              "presto-player"
            )}
            checked={analytics?.use_existing_tag}
            onChange={(use_existing_tag) =>
              updateAnalytics({ use_existing_tag })
            }
          />

          <TextControl
            label={__("Measurement ID", "presto-player")}
            help={__(
              "Enter a Google Analytics Measurement ID, which can be found on your analytics admin page.",
              "presto-player"
            )}
            value={analytics?.measurement_id}
            onChange={(measurement_id) => updateAnalytics({ measurement_id })}
          />
        </Group>

        <Group
          title={__("YouTube", "presto-player")}
          description={__("Settings for YouTube videos.", "presto-player")}
        >
          <ToggleControl
            className="presto-player__setting--youtube-nocookie"
            label={__("Privacy-Enhanced Mode", "presto-player")}
            help={__(
              "Embed YouTube videos without using cookies that track viewing behaviour.",
              "presto-player"
            )}
            checked={youtube?.nocookie}
            onChange={(nocookie) => updateYoutube({ nocookie })}
          />

          <TextControl
            label={__("Channel ID", "presto-player")}
            help={
              <div>
                {__(
                  "Enter the ID of your channel to enable Youtube Subscribe button functionality.",
                  "presto-player"
                )}{" "}
                <ExternalLink href="https://support.google.com/youtube/answer/3250431?hl=en">
                  {__("Find my channel id", "presto-player")}
                </ExternalLink>
              </div>
            }
            value={youtube?.channel_id}
            onChange={(channel_id) => updateYoutube({ channel_id })}
          />
        </Group>

        {showEmailIntegration() && (
          <Group
            hideSaveButton={true}
            title={__("Email Capture", "presto-player")}
            description={__(
              "Integrate Presto Player with an email provider for email capture.",
              "presto-player"
            )}
            disabled={disabled()}
          >
            <ActiveCampaign />
            <FluentCRM />
            <Mailchimp />
            <MailerLite />
            {prestoPlayer?.proVersion &&
              semverCompare(prestoPlayer?.proVersion, "1.2.0") >= 0 && (
                <Webhooks />
              )}
            <EmailExport />
          </Group>
        )}

        {!window?.prestoPlayer?.isSetup?.bunny ? (
          <Group>
            <CTA
              className="presto-player__setting--bunny-cta"
              option={{
                name: __("Bunny.net Video", "presto-player"),
                help: __(
                  "To get started with Bunny.net, add a Bunny.net video to your page.",
                  "presto-player"
                ),
                type: "cta",
                button: {
                  text: __("Learn More", "presto-player"),
                  link: "https://prestoplayer.com/secure-video-with-bunny-net",
                  target: "_blank",
                },
              }}
            />
          </Group>
        ) : (
          bunnySettings()
        )}

        <Group
          title={__("Contribute to Presto Player", "presto-player")}
          description={__(
            "Help us improve Presto Player by sharing non-sensitive usage data.",
            "presto-player"
          )}
        >
          <ToggleControl
            className="presto-player__setting--usage-tracking"
            label={__("Contribute to Presto Player", "presto-player")}
            help={
              <div>
                {__(
                  "Collect non-sensitive information from your website, such as the PHP version and features used, to help us fix bugs faster, make smarter decisions, and build features that actually matter to you. ",
                  "presto-player"
                )}{" "}
                <ExternalLink href="https://prestoplayer.com/share-usage-data/">
                  {__("Learn More", "presto-player")}
                </ExternalLink>
              </div>
            }
            checked={usageTracking === "yes" || usageTracking === true}
            onChange={(enabled) =>
              setUsageTracking(enabled ? "yes" : "no")
            }
          />
        </Group>
      </Page>
    </>
  );
};
