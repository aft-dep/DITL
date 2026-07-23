/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from "@wordpress/interactivity";
const { __ } = wp.i18n;

const { state, actions } = store("presto-player/popup", {
  state: {
    activePopupId: null,
    screenReaderText: "",
    triggerRef: null,
    inertElements: [],
    get overlayEnabled() {
      const ctx = getContext();
      return state?.activePopupId === ctx?.id;
    },

    /**
     * Gets the ARIA role attribute for the popup.
     *
     * @return {string|null} 'dialog' if overlay is open, null otherwise.
     */
    get roleAttribute() {
      return state?.overlayEnabled ? "dialog" : null;
    },

    /**
     * Gets the ARIA modal attribute for the popup.
     *
     * @return {string|null} 'true' if overlay is open, null otherwise.
     */
    get ariaModal() {
      return state?.overlayEnabled ? "true" : null;
    },
  },

  actions: {
    showPopup() {
      const ctx = getContext();
      const { ref } = getElement();

      // Update the active popup id for the current popup.
      state.activePopupId = ctx?.id;

      // make all children of the document inert exempt .presto-popup__overlay.
      document
        .querySelectorAll("body > :not(.presto-popup__overlay)")
        .forEach((el) => {
          if (!el?.hasAttribute("inert")) {
            el?.setAttribute("inert", "");
            state?.inertElements?.push(el);
          }
        });

      // Set the trigger button ref. This is used to focus the trigger button when the popup is closed.
      state.triggerRef = ref;
    },
    hidePopup() {
      // Clear the active popup id.
      state.activePopupId = null;

      // remove inert attribute from all children of the document
      (state?.inertElements || []).forEach((el) => {
        el?.removeAttribute("inert");
      });
      state.inertElements = [];

      // Focus the trigger button when the popup is closed.
      setTimeout(() => {
        state?.triggerRef?.focus();
      }, 0);

      // Set the screen reader text to an empty string when the popup is closed.
      state.screenReaderText = "";
    },
  },
  callbacks: {
    setOverlayFocus() {
      if (!state?.overlayEnabled) {
        return;
      }
      // Moves the focus to the dialog when it opens.
      const { ref } = getElement();

      // Make sure the dialog is rendered and visible before focusing.
      setTimeout(() => {
        ref?.focus();
      }, 0);

      // If the popup is open, set the screen reader text.
      state.screenReaderText = __(
        "Presto Popup dialog opened.",
        "presto-player"
      );
    },
    handleKeydown(event) {
      // Opens the popup when the user presses the enter key.
      if (event?.key === "Enter" && !state?.overlayEnabled) {
        actions.showPopup();
      }
      // Closes the popup when the user presses the escape key.
      if (event?.key === "Escape" && state?.overlayEnabled) {
        actions.hidePopup();
      }
    },
    updatePopupList() {
      // Update the popup list for the current popup.
      const ctx = getContext();
      ctx.popupList = state?.overlayEnabled ? [ctx?.id] : [];
    },
  },
});
