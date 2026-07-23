/** @jsx jsx */
import { css, jsx } from "@emotion/core";
import { useState } from "@wordpress/element";
import { useDispatch } from "@wordpress/data";
import { store as coreStore } from "@wordpress/core-data";
import { store as noticesStore } from "@wordpress/notices";
import { __ } from "@wordpress/i18n";
import ProvidersPlaceholder from "../ProvidersPlaceholder";
import providerIcons from "../ProvidersPlaceholder/icons";

/**
 * MediaProviders component for handling common video operations
 *
 * @param {Object} props Component props
 * @param {Function} props.onSyncedMediaCreated Callback when synced video is created
 * @param {Function} props.onSelect Callback when provider is selected
 * @param {Function} props.onSelectMedia Callback when existing media is selected
 * @param {boolean} props.sync Whether to sync media to media hub
 */
const MediaProviders = ({
  onSyncedMediaCreated,
  onSelect,
  onSelectMedia,
  sync = false,
}) => {
  const { saveEntityRecord } = useDispatch(coreStore);
  const { createErrorNotice } = useDispatch(noticesStore);
  const [saving, setSaving] = useState(false);

  const providers = [
    {
      id: "self-hosted",
      name: __("Video", "presto-player"),
      icon: providerIcons?.video,
      premium: false,
      hasAccess: true,
    },
    {
      id: "youtube",
      name: __("YouTube", "presto-player"),
      icon: providerIcons?.youtube,
      premium: false,
      hasAccess: true,
    },
    {
      id: "vimeo",
      name: __("Vimeo", "presto-player"),
      icon: providerIcons?.vimeo,
      premium: false,
      hasAccess: true,
    },
    {
      id: "audio",
      name: __("Audio", "presto-player"),
      icon: providerIcons?.audio,
      premium: false,
      hasAccess: true,
    },
    {
      id: "bunny",
      name: __("Bunny.net", "presto-player"),
      icon: providerIcons?.bunny,
      premium: true,
      hasAccess: !!prestoPlayer?.isPremium,
    },
  ];

  // Create a video with media hub sync
  const createSyncedVideo = async (provider) => {
    if (saving) return;
    try {
      setSaving(true);
      const { id } = await saveEntityRecord(
        "postType",
        "pp_video_block",
        {
          status: "publish",
          content: `<!-- wp:presto-player/reusable-edit -->
            <div class="wp-block-presto-player-reusable-edit"><!-- wp:presto-player/${provider} /--></div>
            <!-- /wp:presto-player/reusable-edit -->`,
        },
        { throwOnError: true }
      );

      if (onSyncedMediaCreated) {
        onSyncedMediaCreated(id);
      }
    } catch (e) {
      createErrorNotice(
        e?.message || __("Something went wrong", "presto-player")
      );
    } finally {
      setSaving(false);
    }
  };

  const handleProviderSelect = (provider) => {
    if (sync) {
      createSyncedVideo(provider);
    } else {
      onSelect(provider);
    }
  };

  return (
    <ProvidersPlaceholder
      loading={saving}
      onSelect={handleProviderSelect}
      onSelectMedia={onSelectMedia}
      providers={providers}
    />
  );
};

export default MediaProviders;
