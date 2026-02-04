/**
 * WP Smart Code - Admin JavaScript
 * @package ECS
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * Main Admin Object
   */
  const ecsAdmin = {
    /**
     * Initialize
     */
    init: function () {
      this.attachEventListeners();
      this.handleEditorNotices();
      this.logReady();
    },

    /**
     * Handle editor success notices
     */
    handleEditorNotices: function () {
      const $notice = $(".ecs-editor-notice");

      if ($notice.length) {
        // Handle dismiss button
        $notice.find(".notice-dismiss").on("click", function () {
          $notice.addClass("fade-out");
          setTimeout(function () {
            $notice.remove();
            // Remove message parameter from URL
            if (window.history && window.history.replaceState) {
              const url = new URL(window.location);
              url.searchParams.delete("message");
              window.history.replaceState({}, "", url);
            }
          }, 300);
        });

        // Auto-hide after 5 seconds
        setTimeout(function () {
          if ($notice.length && !$notice.hasClass("fade-out")) {
            $notice.addClass("fade-out");
            setTimeout(function () {
              $notice.remove();
              // Remove message parameter from URL
              if (window.history && window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete("message");
                window.history.replaceState({}, "", url);
              }
            }, 300);
          }
        }, 5000);
      }
    },

    /**
     * Attach event listeners
     */
    attachEventListeners: function () {
      // New snippet button
      $(document).on(
        "click",
        "#ecs-new-snippet",
        this.openNewSnippetModal.bind(this)
      );

      // Edit buttons - now handled by direct links to editor page
      // Removed: edit buttons now use href links instead of modal

      // Delete buttons
      $(document).on(
        "click",
        ".ecs-btn-delete",
        this.handleDeleteSnippet.bind(this)
      );

      // Toggle switch is handled by admin-snippets.js
      // Removed duplicate handler that was causing conflicts

      // Import button
      $(document).on(
        "click",
        "#ecs-btn-import",
        this.openImportModal.bind(this)
      );

      // Import modal close buttons
      $(document).on(
        "click",
        "#ecs-import-modal-close, #ecs-import-cancel",
        this.closeImportModal.bind(this)
      );

      // Modal close buttons
      $(document).on(
        "click",
        "#ecs-modal-close, #ecs-modal-cancel",
        this.closeModal.bind(this)
      );

      // Save snippet button
      $(document).on("click", "#ecs-save-snippet", this.saveSnippet.bind(this));

      // Close modal on backdrop click
      $(document).on("click", ".ecs-modal", function (e) {
        if ($(e.target).hasClass("ecs-modal")) {
          ecsAdmin.closeModal();
        }
      });

      // Close modal on Escape key
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && $("#ecs-snippet-modal").is(":visible")) {
          ecsAdmin.closeModal();
        }
      });
    },

    /**
     * Open new snippet modal
     */
    openNewSnippetModal: function (e) {
      e.preventDefault();

      // Reset form
      $("#ecs-snippet-form")[0].reset();
      $("#ecs-snippet-id").val("");
      $("#ecs-modal-title").text("Add New Snippet");

      // Show modal
      $("#ecs-snippet-modal").fadeIn(200);
    },

    /**
     * Open edit snippet modal
     * DEPRECATED: Edit now uses dedicated editor page
     */
    openEditSnippetModal: function (e) {
      // This function is deprecated and no longer used
      // Edit functionality now redirects to the editor page
    },

    /**
     * Save snippet (create or update)
     */
    saveSnippet: function (e) {
      e.preventDefault();

      // Validate form
      const form = document.getElementById("ecs-snippet-form");
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      // Get form data
      const snippetId = $("#ecs-snippet-id").val();
      const data = {
        title: $("#ecs-snippet-title").val(),
        slug: $("#ecs-snippet-slug").val(),
        type: $("#ecs-snippet-type").val(),
        code: $("#ecs-snippet-code").val(),
        active: $("#ecs-snippet-active").is(":checked"),
      };

      // Show loading
      this.showLoading();

      // Determine if creating or updating
      const isNew = !snippetId;
      const method = isNew ? "POST" : "PUT";
      const path = isNew ? "/ecs/v1/snippets" : `/ecs/v1/snippets/${snippetId}`;

      // Save via REST API
      wp.apiFetch({
        path: path,
        method: method,
        data: data,
      })
        .then((snippet) => {
          this.showSuccess(
            isNew
              ? "Snippet created successfully!"
              : "Snippet updated successfully!"
          );
          this.closeModal();

          // Reload page to show updated data
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        })
        .catch((error) => {
          this.showError(
            "Failed to save snippet: " + (error.message || "Unknown error")
          );
        })
        .finally(() => {
          this.hideLoading();
        });
    },

    /**
     * Handle delete snippet
     */
    handleDeleteSnippet: function (e) {
      e.preventDefault();
      const $button = $(e.currentTarget);
      const snippetId = $button.data("snippet-id");
      const $row = $button.closest("tr");

      // Confirm deletion
      if (!confirm(ecsData.i18n.confirmDelete)) {
        return;
      }

      // Show loading state
      $button.prop("disabled", true).text(ecsData.i18n.loading);
      this.showLoading();

      // Delete via REST API
      wp.apiFetch({
        path: `/ecs/v1/snippets/${snippetId}`,
        method: "DELETE",
      })
        .then((response) => {
          this.showSuccess("Snippet deleted successfully!");

          // Remove row with animation
          $row.fadeOut(300, function () {
            $(this).remove();

            // Check if table is now empty
            if ($(".ecs-snippet-row").length === 0) {
              window.location.reload();
            }
          });
        })
        .catch((error) => {
          this.showError(
            "Failed to delete snippet: " + (error.message || "Unknown error")
          );
          $button.prop("disabled", false).text("Delete");
        })
        .finally(() => {
          this.hideLoading();
        });
    },

    /**
     * Handle toggle snippet active status
     */
    handleToggleSnippet: function (e) {
      const $toggle = $(e.currentTarget);
      const snippetId = $toggle.data("snippet-id");
      const isActive = $toggle.is(":checked");
      const $row = $toggle.closest("tr");

      // Show loading state
      $toggle.prop("disabled", true);
      this.showLoading();

      // Update via REST API
      wp.apiFetch({
        path: `/ecs/v1/snippets/${snippetId}`,
        method: "PUT",
        data: {
          active: isActive,
        },
      })
        .then((response) => {
          // Update status badge
          const $statusBadge = $row.find(".column-status .badge");
          if (isActive) {
            $statusBadge
              .removeClass("status-inactive")
              .addClass("status-active")
              .text("Active");
          } else {
            $statusBadge
              .removeClass("status-active")
              .addClass("status-inactive")
              .text("Inactive");
          }

          this.showSuccess(
            `Snippet ${isActive ? "activated" : "deactivated"} successfully!`
          );
        })
        .catch((error) => {
          // Revert toggle state on error
          $toggle.prop("checked", !isActive);
          this.showError(
            `Failed to ${isActive ? "activate" : "deactivate"} snippet: ${
              error.message || "Unknown error"
            }`
          );
        })
        .finally(() => {
          $toggle.prop("disabled", false);
          this.hideLoading();
        });
    },

    /**
     * Close modal
     */
    closeModal: function () {
      $("#ecs-snippet-modal").fadeOut(200);
      $("#ecs-snippet-form")[0].reset();
    },

    /**
     * Show loading overlay
     */
    showLoading: function () {
      $("#ecs-loading-overlay").fadeIn(200);
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function () {
      $("#ecs-loading-overlay").fadeOut(200);
    },

    /**
     * Show success message
     */
    showSuccess: function (message) {
      this.showNotice(message, "success");
    },

    /**
     * Show error message
     */
    showError: function (message) {
      this.showNotice(message, "error");
    },

    /**
     * Show notice
     */
    showNotice: function (message, type) {
      // Remove existing notices
      $(".ecs-notice").remove();

      // Create notice
      const $notice = $("<div>", {
        class: `notice notice-${type} is-dismissible ecs-notice`,
        html: `<p>${message}</p>`,
      });

      // Add dismiss button
      $notice.append(
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>'
      );

      // Insert notice
      $(".ecs-admin-page h1").after($notice);

      // Handle dismiss
      $notice.find(".notice-dismiss").on("click", function () {
        $notice.fadeOut(200, function () {
          $(this).remove();
        });
      });

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        $notice.fadeOut(200, function () {
          $(this).remove();
        });
      }, 5000);

      // Scroll to notice
      $("html, body").animate(
        {
          scrollTop: $notice.offset().top - 100,
        },
        300
      );
    },

    /**
     * Open import modal
     */
    openImportModal: function (e) {
      e.preventDefault();
      $("#ecs-import-modal").fadeIn(200);
      // Reset form
      $("#ecs-import-form")[0].reset();
    },

    /**
     * Close import modal
     */
    closeImportModal: function (e) {
      e.preventDefault();
      $("#ecs-import-modal").fadeOut(200);
    },

    /**
     * Log ready
     */
    logReady: function () {
      // Admin ready
    },
  };

  /**
   * Tools Page Functionality
   */
  const ecsTools = {
    /**
     * Initialize
     */
    init: function () {
      this.attachEventListeners();
    },

    /**
     * Attach event listeners
     */
    attachEventListeners: function () {
      // Copy system info button
      $(document).on(
        "click",
        "#ecs-copy-system-info",
        this.copySystemInfo.bind(this)
      );
    },

    /**
     * Copy system info to clipboard
     */
    copySystemInfo: function (e) {
      e.preventDefault();
      const textarea = document.getElementById("ecs-system-info-text");

      if (textarea) {
        textarea.select();
        document.execCommand("copy");

        // Visual feedback
        const $button = $(e.currentTarget);
        const originalText = $button.html();

        $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
        $button.addClass("button-primary");

        setTimeout(function () {
          $button.html(originalText);
          $button.removeClass("button-primary");
        }, 2000);
      }
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    ecsAdmin.init();

    // Initialize tools page if on tools page
    if ($(".ecs-tools-panel").length) {
      ecsTools.init();
    }
  });
})(jQuery);
