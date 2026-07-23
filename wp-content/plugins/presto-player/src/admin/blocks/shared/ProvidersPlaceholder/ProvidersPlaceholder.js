/** @jsx jsx */
import { css, jsx } from "@emotion/core";
import {
  Placeholder,
  Flex,
  FlexItem,
  Spinner,
  Button,
  MenuItem,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useDispatch } from "@wordpress/data";
import VideoProvider from "./components/VideoProvider";
import providerIcons from "./icons";
import Separator from "./components/Separator";
import SelectMediaDropdown from "../components/SelectMediaDropdown";
import VideoIcon from "../components/VideoIcon";

const ProvidersPlaceholder = ({
  loading,
  onSelect,
  onSelectMedia = null,
  providers = [],
}) => {
  const { dispatch } = useDispatch();

  if (loading) {
    return (
      <Placeholder
        css={css`
          &.components-placeholder {
            padding: 16px;
          }
        `}
      >
        <Spinner />
      </Placeholder>
    );
  }

  return (
    <Placeholder
      css={css`
        &.components-placeholder {
          padding: 32px;
        }
      `}
      label={
        <>
          <Flex
            direction="column"
            css={css`
              margin-bottom: 4px;
            `}
            gap="16px"
          >
            <Flex justify="flex-start">
              {providerIcons?.mediaHubBlock}
              <h1
                css={css`
                  font-size: 24px !important;
                  font-weight: 500 !important;
                  margin: 0px !important;
                `}
              >
                {__("Presto Player", "presto-player")}
              </h1>
            </Flex>
            <Flex>
              <p
                css={css`
                  font-size: 14px !important;
                  font-weight: 300 !important;
                  margin: 0px !important;
                `}
              >
                {__("Choose a video type to get started.", "presto-player")}
              </p>
            </Flex>
          </Flex>
        </>
      }
    >
      <Flex
        direction="column"
        css={css`
          max-width: 540px;
          width: 100%;
        `}
        gap="20px"
      >
        <Flex
          justify={"start"}
          css={css`
            width: 100%;
            max-width: 100%;
          `}
          wrap="wrap"
          gap="20px"
        >
          {(providers || []).map((provider) => (
            <FlexItem key={provider?.id}>
              <VideoProvider
                provider={provider?.name}
                onSelect={() =>
                  provider?.hasAccess
                    ? onSelect(provider?.id)
                    : dispatch("presto-player/player").setProModal(true)
                }
                icon={provider?.icon}
                pro={provider?.premium && !provider?.hasAccess}
              />
            </FlexItem>
          ))}
        </Flex>
        {onSelectMedia && (
          <>
            <Separator icon={providerIcons.line} />
            <Flex>
              <SelectMediaDropdown
                popoverProps={{ placement: "bottom-start" }}
                onSelect={({ id }) => onSelectMedia(id)}
                renderToggle={({ isOpen, onToggle }) => (
                  <Button
                    variant="primary"
                    onClick={onToggle}
                    aria-expanded={isOpen}
                  >
                    {__("Select media", "presto-player")}
                  </Button>
                )}
                renderItem={({ item, onSelect }) => {
                  const { id, title, details } = item;
                  const { type, name } = details || {};
                  const thumbnail =
                    item?._embedded?.["wp:featuredmedia"]?.[0]?.source_url ||
                    "";
                  return (
                    <MenuItem
                      icon={<VideoIcon thumbnail={thumbnail} type={type} />}
                      iconPosition="left"
                      suffix={type ? name : __("Choose media", "presto-player")}
                      onClick={() => onSelect(item)}
                      key={id}
                      css={css`
                        .components-menu-item__item {
                          white-space: nowrap;
                          overflow: hidden;
                          text-overflow: ellipsis;
                          display: inline-block;
                          text-align: left;
                        }
                      `}
                    >
                      {title || __("Untitled", "presto-player")}
                    </MenuItem>
                  );
                }}
              />
            </Flex>
          </>
        )}
      </Flex>
    </Placeholder>
  );
};

export default ProvidersPlaceholder;
