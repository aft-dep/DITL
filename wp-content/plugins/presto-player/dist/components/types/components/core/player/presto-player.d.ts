import { EventEmitter } from '../../../stencil-public-runtime';
import { ActionBarConfig, presetAttributes, DynamicOverlay, i18nConfig, PrestoConfig, SearchBarConfig, prestoBranding, prestoChapters, blockAttributes, YoutubeConfig } from '../../../interfaces';
export declare class PrestoPlayer {
    iconUrl: string;
    src: string;
    type: string;
    autoplay: boolean;
    preload: 'metadata' | 'none' | 'auto';
    poster: string;
    playsinline: boolean;
    isAdmin: boolean;
    direction?: 'rtl';
    css?: string;
    currentTime: number;
    mediaTitle: string;
    classes: string;
    audioAttributes: object;
    markers: any;
    automations: boolean;
    providerVideoId: string;
    videoId: number;
    analytics: boolean;
    provider: string;
    lazyLoadYoutube: boolean;
    private _preset;
    get preset(): presetAttributes;
    set preset(value: string | presetAttributes);
    private _branding;
    get branding(): prestoBranding;
    set branding(value: string | prestoBranding);
    private _chapters;
    get chapters(): prestoChapters;
    set chapters(value: string | prestoChapters);
    private _overlays;
    get overlays(): Array<DynamicOverlay>;
    set overlays(value: string | Array<DynamicOverlay>);
    private _tracks;
    get tracks(): {
        label: string;
        src: string;
        srcLang: string;
    }[];
    set tracks(value: string | Array<{
        label: string;
        src: string;
        srcLang: string;
    }>);
    private _blockAttributes;
    get blockAttributes(): blockAttributes;
    set blockAttributes(value: string | blockAttributes);
    private _config;
    get config(): PrestoConfig;
    set config(value: string | PrestoConfig);
    private _youtube;
    get youtube(): YoutubeConfig;
    set youtube(value: string | YoutubeConfig);
    private _actionBar;
    get actionBar(): ActionBarConfig;
    set actionBar(value: string | ActionBarConfig);
    private _i18n;
    get i18n(): i18nConfig;
    set i18n(value: string | i18nConfig);
    private _search;
    get search(): SearchBarConfig;
    set search(value: string | SearchBarConfig);
    /**
     * This element
     */
    el: HTMLElement;
    /**
     * Component loaded
     */
    loaded: EventEmitter<boolean>;
    currentMediaPlayer: EventEmitter<object>;
    playerReady: EventEmitter<object>;
    playedMedia: EventEmitter<object>;
    pausedMedia: EventEmitter<object>;
    endedMedia: EventEmitter<object>;
    /**
     * State() variables
     */
    player: any;
    playerEl?: HTMLVideoElement | HTMLElement;
    shouldLazyLoad: boolean;
    mutedPreview: boolean;
    duration: number;
    isSticky: boolean;
    videoHeight: number;
    playClass: string;
    previouslyPlaying: boolean;
    ctaActive: boolean;
    emailActive: boolean;
    actionBarActive: boolean;
    ready: boolean;
    private intersectionObserver;
    private resizeObserver;
    private overlaysComponent;
    private components;
    /**
     * Play video
     * @returns Plyr
     */
    play(unmute?: boolean): Promise<void>;
    /**
     * Go to and play
     * @param time number
     */
    goToAndPlay(time: number): Promise<void>;
    handleScroll(ev: any): void;
    /**
     * Play video
     * @returns Plyr
     */
    restart(): Promise<any>;
    /**
     * Pause video
     * @returns Plyr
     */
    pause(): Promise<any>;
    /**
     * Pause video
     * @returns Plyr
     */
    stop(): Promise<any>;
    /**
     * Toggle Fullscreen
     * @returns Plyr
     */
    fullscreenToggle(open: boolean): Promise<any>;
    /**
     * Add an event listener for the specified event.
     * @param event String
     * @param func Function
     * @returns Plyr
     */
    on(event: string, func: Function): Promise<any>;
    /**
     * Add an event listener for the specified event.
     * @param event String
     * @param func Function
     * @returns Plyr
     */
    once(event: string, func: Function): Promise<any>;
    /**
     * Remove an event listener for the specified event.
     * @param event String
     * @param func Function
     * @returns Plyr
     */
    off(event: string, func: Function): Promise<any>;
    handleCurrentTimeChange(val: any): Promise<void>;
    /**
     * Handle sticky change
     */
    handleStickyChange(): void;
    /**
     * Get player config
     * @returns object
     */
    getConfig(): {
        iconUrl?: string;
        markers: {
            enabled: boolean;
            points: any[];
        };
        ratio: string;
        invertTime: boolean;
        storage: {
            enabled?: boolean;
            key?: string;
        };
        keyboard: {
            focused: boolean;
            global: boolean;
        };
        resetOnEnd: boolean;
        vimeo: {
            byline: boolean;
            portrait: boolean;
            title: boolean;
            speed: boolean;
            transparent: boolean;
            customControls: boolean;
            premium: boolean;
            playsinline: boolean;
        };
        youtube: {
            rel: number;
            showinfo: number;
            iv_load_policy: number;
            modestbranding: number;
            customControls: boolean;
            noCookie: boolean;
            playsinline: boolean;
        };
        tooltips: {
            controls: boolean;
            seek: boolean;
        };
        i18n: i18nConfig;
        poster?: string;
        provider_video_id?: string;
        provider?: string;
        id: number;
        title: string;
        blockAttributes: {
            type: string;
            color?: string;
            id?: number;
            title?: string;
            src?: string;
            poster?: string;
            playsInline?: boolean;
            autoplay?: boolean;
            mutedPreview?: {
                enabled?: boolean;
                captions?: boolean;
            };
            mutedOverlay?: import("../../../interfaces").MutedOverlay;
            ratio?: string;
            constructor: Function;
            toString(): string;
            toLocaleString(): string;
            valueOf(): Object;
            hasOwnProperty(v: PropertyKey): boolean;
            isPrototypeOf(v: Object): boolean;
            propertyIsEnumerable(v: PropertyKey): boolean;
        };
        src: string;
        ajaxProgress: boolean;
        analytics: boolean;
        preset: presetAttributes;
        automations: boolean;
        mutedPreview: {
            enabled: boolean;
        };
        loop: {
            active: boolean;
        };
        chapters: prestoChapters;
        controls: string[];
        settings: string[];
        hideControls: boolean;
        captions: {
            active: boolean;
            language: string;
            update: boolean;
        };
        logo: string;
        logo_width: number;
        hide_logo: boolean;
        lazy_load_youtube: boolean;
        save_player_position: boolean;
        sticky_scroll: boolean;
        play_video_viewport: boolean;
        autoplay: boolean;
    };
    /**
     * Get player data
     * @returns object
     */
    getPlayerData(): {
        selector: HTMLElement | HTMLVideoElement;
        src: string;
        preload: "none" | "metadata" | "auto";
        provider: string;
        config: {
            iconUrl?: string;
            markers: {
                enabled: boolean;
                points: any[];
            };
            ratio: string;
            invertTime: boolean;
            storage: {
                enabled?: boolean;
                key?: string;
            };
            keyboard: {
                focused: boolean;
                global: boolean;
            };
            resetOnEnd: boolean;
            vimeo: {
                byline: boolean;
                portrait: boolean;
                title: boolean;
                speed: boolean;
                transparent: boolean;
                customControls: boolean;
                premium: boolean;
                playsinline: boolean;
            };
            youtube: {
                rel: number;
                showinfo: number;
                iv_load_policy: number;
                modestbranding: number;
                customControls: boolean;
                noCookie: boolean;
                playsinline: boolean;
            };
            tooltips: {
                controls: boolean;
                seek: boolean;
            };
            i18n: i18nConfig;
            poster?: string;
            provider_video_id?: string;
            provider?: string;
            id: number;
            title: string;
            blockAttributes: {
                type: string;
                color?: string;
                id?: number;
                title?: string;
                src?: string;
                poster?: string;
                playsInline?: boolean;
                autoplay?: boolean;
                mutedPreview?: {
                    enabled?: boolean;
                    captions?: boolean;
                };
                mutedOverlay?: import("../../../interfaces").MutedOverlay;
                ratio?: string;
                constructor: Function;
                toString(): string;
                toLocaleString(): string;
                valueOf(): Object;
                hasOwnProperty(v: PropertyKey): boolean;
                isPrototypeOf(v: Object): boolean;
                propertyIsEnumerable(v: PropertyKey): boolean;
            };
            src: string;
            ajaxProgress: boolean;
            analytics: boolean;
            preset: presetAttributes;
            automations: boolean;
            mutedPreview: {
                enabled: boolean;
            };
            loop: {
                active: boolean;
            };
            chapters: prestoChapters;
            controls: string[];
            settings: string[];
            hideControls: boolean;
            captions: {
                active: boolean;
                language: string;
                update: boolean;
            };
            logo: string;
            logo_width: number;
            hide_logo: boolean;
            lazy_load_youtube: boolean;
            save_player_position: boolean;
            sticky_scroll: boolean;
            play_video_viewport: boolean;
            autoplay: boolean;
        };
        isAdmin: boolean;
    };
    /**
     * Create the video player
     * @returns void
     */
    createPlayer(): Promise<any>;
    handlePlayerElementChange(): void;
    /**
     * Handle muted preview change
     * @returns void
     */
    handleMutedPreview(val: any, prev: any): void;
    onPlayerReady(): void;
    /**
     * Update player state with events
     */
    handlePlayerEvents(player: any): void;
    /**
     * Handle lazy load changes
     * @returns
     */
    handleLazyLoadChange(): void;
    /**
     * Should we lazy load the video?
     * @returns boolean
     */
    shouldLazyLoadVideo(): boolean;
    /**
     * Initialize data
     */
    componentWillLoad(): void;
    /**
     * Create Player
     */
    componentDidLoad(): Promise<void>;
    /**
     * Init player
     * @returns plyr object
     */
    initialize(): Promise<any>;
    renderSkins(): any;
    /**
     * On player reload
     * @param ev
     */
    onReload(action: any): Promise<void>;
    /**
     * Sync video height as height changes
     */
    syncVideoHeight(): Promise<void>;
    handleRestartVideo(): void;
    handlePlayVideo(): void;
    handlePauseVideo(e: any): void;
    /**
     * Handle tab visibility change
     * @returns void
     */
    playVideoOnlyInViewport(): void;
    /**
     * Tracks the visibility of the video
     * based on intersection
     */
    trackIntersection(): void;
    /**
     * Handle the intersection
     * @param element
     * @returns
     */
    handleVisibilityIntersection(element: any): void;
    /**
     * Handle sticky scroll based on intersection
     */
    handleStickyScroll(element: any): void;
    /**
     * Handle play change on visibility condition
     * @param condition
     * @returns
     */
    handleVisibilityPlayChange(element: any): void;
    /**
     * Render the muted overlay
     * @returns JSX
     */
    renderMutedOverlay(): any;
    /**
     * Render the video
     * @returns JSX
     */
    renderVideo(): any;
    renderVideoCTA(): void;
    renderAudioCTA(): any;
    /**
     * Render email overlay
     * @returns JSX
     */
    renderEmailOverlay(): void;
    renderAudioEmail(): any;
    /**
     * Render email overlay
     * @returns JSX
     */
    renderActionBar(): any;
    /**
     * Render search overlay
     * @returns JSX
     */
    renderSearchBar(): void;
    stickyPositionClass(): string;
    /** We append this instead of using JSX because we want it to work in fullscreen. */
    renderDynamicOverlays(): void;
    handleCtaStateChange(ev: any): void;
    handleEmailStateChange(ev: any): void;
    handleActionBarStateChange(ev: any): void;
    /**
     * Clean up resources when component is disconnected
     */
    disconnectedCallback(): void;
    /**
     * Render the component
     * @returns JSX
     */
    render(): any;
}
