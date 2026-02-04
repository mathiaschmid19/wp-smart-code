/**
 * WP Smart Code - Admin Snippets List Table JavaScript
 * Handles AJAX interactions for the snippets list table
 *
 * @package ECS
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * Admin Snippets List Table Object
   */
  const ECSSnippetsList = {
    /**
     * Debounce timer for rapid actions
     */
    debounceTimer: null,

    /**
     * Initialize the snippets list functionality
     */
    init: function () {
      this.attachEventListeners();
      this.initBulkActions();
    },

    /**
     * Attach event listeners
     */
    attachEventListeners: function () {
      // Toggle snippet status (old button style)
      $(document).on(
        "click",
        ".ecs-toggle-snippet",
        this.handleToggleSnippet.bind(this)
      );

      // Toggle snippet status (new toggle switch)
      $(document).on(
        "change",
        ".ecs-snippet-toggle",
        this.handleToggleSwitch.bind(this)
      );

      // Delete single snippet
      $(document).on(
        "click",
        ".ecs-delete-snippet",
        this.handleDeleteSnippet.bind(this)
      );

      // Bulk actions form submission
      $(document).on(
        "submit",
        "#posts-filter",
        this.handleBulkAction.bind(this)
      );

      // Bulk action dropdown change
      $(document).on(
        "change",
        "#bulk-action-selector-top, #bulk-action-selector-bottom",
        this.validateBulkAction.bind(this)
      );
    },

    /**
     * Initialize bulk actions
     */
    initBulkActions: function () {
      // Handle bulk action buttons
      $(document).on(
        "click",
        "#doaction, #doaction2",
        this.processBulkAction.bind(this)
      );
    },

    /**
     * Handle toggle snippet status with debouncing
     */
    handleToggleSnippet: function (e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const snippetId = $button.data("snippet-id");
      const currentStatus = $button.data("current-status");
      const newStatus = currentStatus ? 0 : 1;

      // Debounce rapid clicks
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
      }

      this.debounceTimer = setTimeout(() => {
        this.toggleSnippetStatus(snippetId, newStatus, $button);
      }, 300);
    },

    /**
     * Handle toggle switch change
     */
    handleToggleSwitch: function (e) {
      const $checkbox = $(e.currentTarget);
      const $toggleSwitch = $checkbox.closest(".ecs-toggle-switch");
      const snippetId = $checkbox.data("snippet-id");
      
      // The checkbox has already changed when 'change' event fires
      // So we read the NEW state directly from the checkbox
      const newStatus = $checkbox.is(":checked") ? 1 : 0;

      // Add loading state
      $toggleSwitch.addClass("loading");

      // Toggle snippet status
      this.toggleSnippetStatusSwitch(
        snippetId,
        newStatus,
        $checkbox,
        $toggleSwitch
      );
    },

    /**
     * Toggle snippet status via AJAX
     */
    toggleSnippetStatus: function (snippetId, newStatus, $button) {
      // Disable button and show loading state
      $button.prop("disabled", true);
      const originalText = $button.text();
      $button.text(ecsData.i18n.loading || "Loading...");

      // Prepare AJAX data
      const ajaxData = {
        action: "ecs_toggle_snippet",
        nonce: ecsData.nonce,
        id: snippetId,
        active: newStatus,
      };

      // Send AJAX request
      $.ajax({
        url: ecsData.ajaxUrl,
        type: "POST",
        data: ajaxData,
        dataType: "json",
        success: (response) => {
          if (response.success) {
            // Update UI immediately
            this.updateSnippetStatusUI(snippetId, newStatus, response.data);
            this.showNotice(response.data.message, "success");
          } else {
            this.showNotice(
              response.data.message || "Failed to update snippet status.",
              "error"
            );
          }
        },
        error: (jqXHR, textStatus, errorThrown) => {
          console.error("AJAX Error:", errorThrown);
          this.showNotice(
            "An error occurred while updating the snippet status.",
            "error"
          );
        },
        complete: () => {
          // Re-enable button and restore text
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    /**
     * Toggle snippet status via AJAX (for toggle switch)
     */
    toggleSnippetStatusSwitch: function (
      snippetId,
      newStatus,
      $checkbox,
      $toggleSwitch
    ) {
      console.log('[ECS] Toggle request:', { snippetId, newStatus });
      
      // Prepare AJAX data
      const ajaxData = {
        action: "ecs_toggle_snippet",
        nonce: ecsData.nonce,
        id: snippetId,
        active: newStatus,
      };

      // Send AJAX request
      $.ajax({
        url: ecsData.ajaxUrl,
        type: "POST",
        data: ajaxData,
        dataType: "json",
        success: (response) => {
          console.log('[ECS] Toggle response:', response);
          
          if (response.success) {
            // Success! The checkbox is already in the correct state
            // Just show visual feedback
            $toggleSwitch.addClass("success-flash");
            setTimeout(() => {
              $toggleSwitch.removeClass("success-flash");
            }, 500);
          } else {
            // Error from server - revert checkbox to its previous state
            $checkbox.prop("checked", !newStatus);
            this.showNotice(
              response.data?.message || "Failed to update snippet status.",
              "error"
            );
          }
        },
        error: (jqXHR, textStatus, errorThrown) => {
          console.error("[ECS] AJAX Error:", { textStatus, errorThrown, response: jqXHR.responseText });
          // Network error - revert checkbox to its previous state
          $checkbox.prop("checked", !newStatus);
          this.showNotice(
            "An error occurred while updating the snippet status.",
            "error"
          );
        },
        complete: () => {
          // Remove loading state
          $toggleSwitch.removeClass("loading");
        },
      });
    },

    /**
     * Update snippet status in the UI
     */
    updateSnippetStatusUI: function (snippetId, newStatus, data) {
      const $row = $(`tr[data-snippet-id="${snippetId}"]`);
      const $statusColumn = $row.find(".column-status");
      const $toggleButton = $row.find(".ecs-toggle-snippet");

      // Update status badge
      const statusClass = newStatus ? "status-active" : "status-inactive";
      const statusText = data.status || (newStatus ? "Active" : "Inactive");

      $statusColumn.html(
        `<span class="badge ${statusClass}">${statusText}</span>`
      );

      // Update toggle button
      $toggleButton.data("current-status", newStatus);
      $toggleButton.text(newStatus ? "Deactivate" : "Activate");

      // Update row class for styling
      $row.toggleClass("active", newStatus).toggleClass("inactive", !newStatus);
    },

    /**
     * Handle delete snippet with confirmation
     */
    handleDeleteSnippet: function (e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const snippetId = $button.data("snippet-id");
      const snippetTitle = $button.data("snippet-title") || "this snippet";
      const isPermanent = $button.hasClass("permanent-delete");

      // Show confirmation dialog
      const confirmMessage = isPermanent
        ? `Are you sure you want to permanently delete "${snippetTitle}"? This action cannot be undone.`
        : `Are you sure you want to move "${snippetTitle}" to trash?`;

      if (!confirm(confirmMessage)) {
        return;
      }

      this.deleteSnippet(snippetId, isPermanent, $button);
    },

    /**
     * Delete snippet via AJAX
     */
    deleteSnippet: function (snippetId, permanent, $button) {
      // Disable button and show loading state
      $button.prop("disabled", true);
      const originalText = $button.text();
      $button.text(ecsData.i18n.loading || "Loading...");

      // Prepare AJAX data
      const ajaxData = {
        action: "ecs_delete_snippet",
        nonce: ecsData.nonce,
        id: snippetId,
        permanent: permanent ? 1 : 0,
      };

      // Send AJAX request
      $.ajax({
        url: ecsData.ajaxUrl,
        type: "POST",
        data: ajaxData,
        dataType: "json",
        success: (response) => {
          if (response.success) {
            // Remove row from table with animation
            const $row = $(`tr[data-snippet-id="${snippetId}"]`);
            $row.fadeOut(300, function () {
              $(this).remove();
              // Check if table is now empty
              if ($(".wp-list-table tbody tr").length === 0) {
                location.reload();
              }
            });

            this.showNotice(response.data.message, "success");
          } else {
            this.showNotice(
              response.data.message || "Failed to delete snippet.",
              "error"
            );
          }
        },
        error: (jqXHR, textStatus, errorThrown) => {
          console.error("AJAX Error:", errorThrown);
          this.showNotice(
            "An error occurred while deleting the snippet.",
            "error"
          );
        },
        complete: () => {
          // Re-enable button and restore text
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    /**
     * Validate bulk action selection
     */
    validateBulkAction: function (e) {
      const $select = $(e.currentTarget);
      const action = $select.val();

      if (action === "-1") {
        return;
      }

      // Check if any items are selected
      const selectedItems = $(
        '.wp-list-table input[name="snippet[]"]:checked'
      ).length;

      if (selectedItems === 0) {
        alert("Please select at least one snippet to perform this action.");
        $select.val("-1");
        return false;
      }
    },

    /**
     * Handle bulk action form submission
     */
    handleBulkAction: function (e) {
      const $form = $(e.currentTarget);
      const action =
        $form.find('select[name="action"]').val() ||
        $form.find('select[name="action2"]').val();

      if (action && action !== "-1") {
        e.preventDefault();
        this.processBulkActionAjax(action, $form);
      }
    },

    /**
     * Process bulk action button clicks
     */
    processBulkAction: function (e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const $form = $button.closest("form");
      const isTop = $button.attr("id") === "doaction";
      const action = isTop
        ? $form.find("#bulk-action-selector-top").val()
        : $form.find("#bulk-action-selector-bottom").val();

      if (!action || action === "-1") {
        alert("Please select an action.");
        return;
      }

      this.processBulkActionAjax(action, $form);
    },

    /**
     * Process bulk action via AJAX
     */
    processBulkActionAjax: function (action, $form) {
      const selectedSnippets = [];
      $form.find('input[name="snippet[]"]:checked').each(function () {
        selectedSnippets.push($(this).val());
      });

      if (selectedSnippets.length === 0) {
        alert("Please select at least one snippet.");
        return;
      }

      // Show confirmation for destructive actions
      if (["delete", "trash"].includes(action)) {
        const confirmMessage =
          action === "delete"
            ? `Are you sure you want to permanently delete ${selectedSnippets.length} snippet(s)? This action cannot be undone.`
            : `Are you sure you want to move ${selectedSnippets.length} snippet(s) to trash?`;

        if (!confirm(confirmMessage)) {
          return;
        }
      }

      // Show loading state
      this.showBulkActionLoading(true);

      // Prepare AJAX data
      const ajaxData = {
        action: "ecs_bulk_action",
        nonce: ecsData.nonce,
        bulk_action: action,
        snippet: selectedSnippets,
      };

      // Send AJAX request
      $.ajax({
        url: ecsData.ajaxUrl,
        type: "POST",
        data: ajaxData,
        dataType: "json",
        success: (response) => {
          if (response.success) {
            this.showNotice(response.data.message, "success");

            // Reload page to reflect changes
            setTimeout(() => {
              location.reload();
            }, 1000);
          } else {
            this.showNotice(
              response.data.message || "Bulk action failed.",
              "error"
            );
          }
        },
        error: (jqXHR, textStatus, errorThrown) => {
          console.error("AJAX Error:", errorThrown);
          this.showNotice(
            "An error occurred while processing the bulk action.",
            "error"
          );
        },
        complete: () => {
          this.showBulkActionLoading(false);
        },
      });
    },

    /**
     * Show/hide bulk action loading state
     */
    showBulkActionLoading: function (show) {
      const $buttons = $("#doaction, #doaction2");

      if (show) {
        $buttons.prop("disabled", true);
        $buttons.each(function () {
          const $btn = $(this);
          $btn.data("original-text", $btn.val());
          $btn.val(ecsData.i18n.loading || "Processing...");
        });
      } else {
        $buttons.prop("disabled", false);
        $buttons.each(function () {
          const $btn = $(this);
          const originalText = $btn.data("original-text");
          if (originalText) {
            $btn.val(originalText);
          }
        });
      }
    },

    /**
     * Show admin notice
     */
    showNotice: function (message, type) {
      // Remove existing notices
      $(".ecs-ajax-notice").remove();

      // Create notice element
      const $notice = $("<div>", {
        class: `notice notice-${type} is-dismissible ecs-ajax-notice`,
        html: `<p>${message}</p>`,
      });

      // Add dismiss button
      $notice.append(
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>'
      );

      // Insert notice after page title
      $(".wrap h1").first().after($notice);

      // Handle dismiss button
      $notice.find(".notice-dismiss").on("click", function () {
        $notice.fadeOut(200, function () {
          $(this).remove();
        });
      });

      // Auto-dismiss success notices after 5 seconds
      if (type === "success") {
        setTimeout(() => {
          $notice.fadeOut(200, function () {
            $(this).remove();
          });
        }, 5000);
      }

      // Scroll to notice
      $("html, body").animate(
        {
          scrollTop: $notice.offset().top - 100,
        },
        300
      );
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    // Only initialize on snippets list page
    if ($(".wp-list-table.snippets").length || $(".tablenav").length) {
      ECSSnippetsList.init();
    }
  });

  // Make available globally for debugging
  window.ECSSnippetsList = ECSSnippetsList;
})(jQuery);
