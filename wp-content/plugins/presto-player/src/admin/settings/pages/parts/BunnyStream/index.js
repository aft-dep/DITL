import { __ } from "@wordpress/i18n";
import { useState, useEffect } from "@wordpress/element";
import { TextControl, Button } from "@wordpress/components";
import { useEntityProp } from "@wordpress/core-data";
import { useDispatch } from "@wordpress/data";
import { store as noticesStore } from "@wordpress/notices";
import apiFetch from "@wordpress/api-fetch";
import PublicStream from "./PublicStream";
import PrivateStream from "./PrivateStream";

export default () => {
  const [publicStream] = useEntityProp(
    "root",
    "site",
    "presto_player_bunny_stream_public"
  );
  const [privateStream] = useEntityProp(
    "root",
    "site",
    "presto_player_bunny_stream_private"
  );
  const [bunnyStream, setBunnyStream] = useEntityProp(
    "root",
    "site",
    "presto_player_bunny_stream"
  );
  const [isRegistering, setIsRegistering] = useState(false);
  const [isClearingCache, setIsClearingCache] = useState(false);
  const [defaultWebhookUrl, setDefaultWebhookUrl] = useState("");
  const [isLoadingDefault, setIsLoadingDefault] = useState(false);
  const { createSuccessNotice, createErrorNotice } = useDispatch(noticesStore);

  const hasPublicLibrary = !!publicStream?.video_library_id;
  const hasPrivateLibrary = !!privateStream?.video_library_id;
  const hasAnyLibrary = hasPublicLibrary || hasPrivateLibrary;

  // Get default webhook URL from backend
  useEffect(() => {
    const fetchDefaultWebhookUrl = async () => {
      if (defaultWebhookUrl || isLoadingDefault) return;
      setIsLoadingDefault(true);
      try {
        const webhookUrlEndpoint =
          prestoPlayer?.transcriptionEndpoints?.webhookUrl;
        if (!webhookUrlEndpoint) {
          console.error("Webhook URL endpoint not available");
          return;
        }
        const response = await apiFetch({
          path: webhookUrlEndpoint,
          method: "GET",
        });
        if (response?.url) {
          setDefaultWebhookUrl(response.url);
        }
      } catch (error) {
        console.error("Failed to fetch default webhook URL:", error);
      } finally {
        setIsLoadingDefault(false);
      }
    };
    fetchDefaultWebhookUrl();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Current webhook URL: use custom if set, otherwise use default
  const currentWebhookUrl =
    bunnyStream?.webhook_url && bunnyStream.webhook_url.trim()
      ? bunnyStream.webhook_url
      : defaultWebhookUrl || "";

  const handleWebhookUrlChange = (value) => {
    setBunnyStream({
      ...bunnyStream,
      webhook_url: value,
    });
  };

  const registerWebhooks = async () => {
    setIsRegistering(true);
    try {
      const promises = [];
      // Use custom URL if provided and not empty, otherwise use default
      const webhookUrl =
        bunnyStream?.webhook_url && bunnyStream.webhook_url.trim()
          ? bunnyStream.webhook_url
          : defaultWebhookUrl;
      const registerWebhookEndpoint =
        prestoPlayer?.transcriptionEndpoints?.registerWebhook;

      if (!registerWebhookEndpoint) {
        createErrorNotice(
          __("Webhook registration endpoint not available.", "presto-player"),
          { type: "snackbar" }
        );
        setIsRegistering(false);
        return;
      }

      if (hasPublicLibrary) {
        promises.push(
          apiFetch({
            path: registerWebhookEndpoint,
            method: "POST",
            data: {
              type: "public",
              webhook_url: webhookUrl,
            },
          })
        );
      }
      if (hasPrivateLibrary) {
        promises.push(
          apiFetch({
            path: registerWebhookEndpoint,
            method: "POST",
            data: {
              type: "private",
              webhook_url: webhookUrl,
            },
          })
        );
      }
      await Promise.all(promises);
      createSuccessNotice(__("Webhook URL Registered", "presto-player"), {
        type: "snackbar",
      });
    } catch (error) {
      createErrorNotice(
        error?.message || __("Failed to register webhook.", "presto-player"),
        { type: "snackbar" }
      );
    } finally {
      setIsRegistering(false);
    }
  };

  const clearCaptionCache = async () => {
    setIsClearingCache(true);
    try {
      const clearCacheEndpoint =
        prestoPlayer?.transcriptionEndpoints?.clearCache;
      if (!clearCacheEndpoint) {
        createErrorNotice(
          __("Clear cache endpoint not available.", "presto-player"),
          { type: "snackbar" }
        );
        setIsClearingCache(false);
        return;
      }
      const result = await apiFetch({
        path: clearCacheEndpoint,
        method: "DELETE",
      });
      createSuccessNotice(
        __("Caption cache cleared successfully.", "presto-player"),
        { type: "snackbar" }
      );
    } catch (error) {
      createErrorNotice(
        error?.message || __("Failed to clear caption cache.", "presto-player"),
        { type: "snackbar" }
      );
    } finally {
      setIsClearingCache(false);
    }
  };

  if (!publicStream) return null;

  return (
    <>
      <h2 style={{ marginTop: "40px" }}>
        {__("Bunny.net Stream", "presto-player")}
      </h2>
      <PublicStream />
      <PrivateStream />

      <h3 style={{ marginTop: "32px" }}>
        {__("Webhook Settings", "presto-player")}
      </h3>
      <p style={{ fontSize: "12px", color: "#757575", marginBottom: "16px" }}>
        {__(
          "Register the webhook URL with Bunny.net to automatically sync captions when automatic caption generation completes.",
          "presto-player"
        )}
      </p>
      <TextControl
        label={__("Webhook URL", "presto-player")}
        value={currentWebhookUrl}
        onChange={handleWebhookUrlChange}
        placeholder={
          isLoadingDefault
            ? __("Loading...", "presto-player")
            : __("Enter webhook URL", "presto-player")
        }
        help={__(
          "The webhook URL to register with Bunny.net. Leave empty to use the default generated URL.",
          "presto-player"
        )}
      />
      <Button
        variant="secondary"
        onClick={registerWebhooks}
        isBusy={isRegistering}
        disabled={isRegistering || !hasAnyLibrary}
        style={{ marginTop: "8px" }}
      >
        {isRegistering
          ? __("Registering...", "presto-player")
          : __("Register Webhook", "presto-player")}
      </Button>
      {!hasAnyLibrary && (
        <p style={{ fontSize: "12px", color: "#757575", marginTop: "8px" }}>
          {__(
            "Connect a Bunny.net library first to register the webhook.",
            "presto-player"
          )}
        </p>
      )}

      <h3 style={{ marginTop: "32px" }}>
        {__("Caption Cache", "presto-player")}
      </h3>
      <p style={{ fontSize: "12px", color: "#757575", marginBottom: "16px" }}>
        {__(
          "Clear cached captions if they are not updating properly. Captions are normally refreshed automatically via webhooks.",
          "presto-player"
        )}
      </p>
      <Button
        variant="secondary"
        onClick={clearCaptionCache}
        isBusy={isClearingCache}
        disabled={isClearingCache || !hasAnyLibrary}
        isDestructive
      >
        {isClearingCache
          ? __("Clearing...", "presto-player")
          : __("Clear Caption Cache", "presto-player")}
      </Button>
    </>
  );
};
