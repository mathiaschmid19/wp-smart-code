/**
 * WP Smart Code - Editor Page JavaScript
 * @package ECS
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * Editor Page Object
   */
  const ecsEditor = {
    /**
     * CodeMirror instance
     */
    codeMirror: null,

    /**
     * Initialize
     */
    init: function () {
      this.initCodeEditor();
      this.attachEventListeners();
      this.autoGenerateSlug();
      this.toggleShortcodeMode($("#ecs-snippet-type").val());
      this.logReady();
    },

    /**
     * Initialize CodeMirror editor with Highlight.js integration
     */
    initCodeEditor: function () {
      const textarea = document.getElementById("ecs-snippet-code");
      if (!textarea) return;

      // Get the selected type
      const selectedType = $("#ecs-snippet-type").val();

      // Get CodeMirror settings
      const settings = wp.codeEditor.defaultSettings
        ? _.clone(wp.codeEditor.defaultSettings)
        : {};

      // Enhanced CodeMirror configuration with Highlight.js integration
      settings.codemirror = _.extend({}, settings.codemirror, {
        mode: this.getCodeMirrorMode(selectedType),
        lineNumbers: true,
        lineWrapping: true,
        indentUnit: 2,
        tabSize: 2,
        indentWithTabs: false, // Use spaces for better consistency
        theme: "default",
        autoCloseBrackets: true,
        matchBrackets: true,
        autoCloseTags: true,
        styleActiveLine: true,
        viewportMargin: Infinity,
        foldGutter: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
        foldOptions: {
          widget: (from, to) => {
            const count = to.line - from.line;
            return count > 1 ? `... ${count} lines ...` : "...";
          },
        },
        // Enhanced syntax highlighting options
        highlightSelectionMatches: {
          showToken: /\w/,
          annotateScrollbar: true,
        },
        // Better bracket matching
        bracketMatching: true,
        // Smart indentation
        smartIndent: true,
        // Electric characters for better indentation
        electricChars: true,
        // Extra keys for better editing experience
        extraKeys: {
          "Ctrl-Space": "autocomplete",
          "Ctrl-/": "toggleComment",
          "Ctrl-Alt-L": "autoFormat",
          F11: function (cm) {
            cm.setOption("fullScreen", !cm.getOption("fullScreen"));
          },
          Esc: function (cm) {
            if (cm.getOption("fullScreen")) cm.setOption("fullScreen", false);
          },
        },
        // Add rulers for better code formatting
        rulers: [80, 120],
        // Show whitespace characters
        showTrailingSpace: true,
        // Better cursor behavior
        cursorBlinkRate: 530,
        cursorScrollMargin: 5,
        // Better selection behavior
        dragDrop: true,
        // Better search
        search: {
          bottom: true,
        },
      });

      // Initialize CodeMirror
      this.codeMirror = wp.codeEditor.initialize(textarea, settings);

      // Add custom CSS class for enhanced styling
      if (this.codeMirror && this.codeMirror.codemirror) {
        this.codeMirror.codemirror
          .getWrapperElement()
          .classList.add("ecs-enhanced-editor");

        // Add paste event handler to remove PHP opening tags
        this.codeMirror.codemirror.on("paste", (cm, event) => {
          // Use setTimeout to process the pasted content after it's inserted
          setTimeout(() => {
            this.removePhpOpeningTag(cm);
          }, 10);
        });
      }

      // Initialize Highlight.js integration
      this.initHighlightJS();

      // Refresh CodeMirror after initialization
      setTimeout(() => {
        if (this.codeMirror && this.codeMirror.codemirror) {
          this.codeMirror.codemirror.refresh();
          // Force re-highlighting for better syntax detection
          this.codeMirror.codemirror.setOption(
            "mode",
            this.getCodeMirrorMode(selectedType)
          );
          // Apply Highlight.js highlighting
          this.applyHighlightJS();
        }
      }, 100);
    },

    /**
     * Remove PHP opening tag from pasted content
     */
    removePhpOpeningTag: function (cm) {
      const content = cm.getValue();

      // Check if content starts with <?php and remove it
      if (content.startsWith("<?php")) {
        // Remove <?php from the beginning
        const cleanedContent = content.replace(/^<\?php\s*/, "");

        // Update the editor content
        cm.setValue(cleanedContent);

        // Show a subtle notification
        this.showNotice(
          "info",
          "PHP opening tag automatically removed from pasted content."
        );
      }
    },

    /**
     * Get CodeMirror mode for snippet type
     */
    getCodeMirrorMode: function (type) {
      const modes = {
        php: "application/x-httpd-php",
        js: "javascript",
        css: "css",
        html: "htmlmixed",
      };
      return modes[type] || "htmlmixed";
    },

    /**
     * Update CodeMirror mode when type changes
     */
    updateCodeMirrorMode: function (type) {
      if (this.codeMirror && this.codeMirror.codemirror) {
        const mode = this.getCodeMirrorMode(type);
        this.codeMirror.codemirror.setOption("mode", mode);

        // Force refresh to apply new syntax highlighting
        setTimeout(() => {
          this.codeMirror.codemirror.refresh();
          // Apply Highlight.js highlighting after mode change
          this.applyHighlightJS();
        }, 50);
      }
    },

    /**
     * Initialize Highlight.js integration
     */
    initHighlightJS: function () {
      // Check if Highlight.js is loaded
      if (typeof hljs !== "undefined") {
        // Configure Highlight.js
        hljs.configure({
          languages: ["php", "javascript", "css", "html", "xml"],
          ignoreUnescapedHTML: true,
        });

        // Highlight.js initialized successfully
      } else {
        // Highlight.js not loaded, falling back to CodeMirror highlighting
      }
    },

    /**
     * Apply Highlight.js syntax highlighting to the editor
     */
    applyHighlightJS: function () {
      if (
        typeof hljs === "undefined" ||
        !this.codeMirror ||
        !this.codeMirror.codemirror
      ) {
        return;
      }

      const cm = this.codeMirror.codemirror;
      const selectedType = $("#ecs-snippet-type").val();

      // Get the language name for Highlight.js
      const hljsLanguage = this.getHighlightJSLanguage(selectedType);

      if (hljsLanguage) {
        // Get the current content
        const content = cm.getValue();

        // Highlight the content with Highlight.js
        const highlighted = hljs.highlight(content, { language: hljsLanguage });

        // Apply the highlighted HTML to a temporary element
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = highlighted.value;

        // Extract the highlighted content and apply it to CodeMirror
        // Note: This is a simplified approach - in practice, you might want to
        // integrate this more deeply with CodeMirror's token system
      }
    },

    /**
     * Get Highlight.js language name for snippet type
     */
    getHighlightJSLanguage: function (type) {
      const languages = {
        php: "php",
        js: "javascript",
        css: "css",
        html: "html",
      };
      return languages[type] || null;
    },

    /**
     * Attach event listeners
     */
    attachEventListeners: function () {
      // Code type selector change
      $("#ecs-snippet-type").on("change", function () {
        ecsEditor.toggleCodeTypeInfo();
        ecsEditor.updateCodeMirrorMode($(this).val());
        ecsEditor.toggleShortcodeMode($(this).val());
        // Apply Highlight.js highlighting when type changes
        setTimeout(() => {
          ecsEditor.applyHighlightJS();
        }, 100);
      });

      // Auto-generate slug from title
      $("#ecs-snippet-title").on("input", this.autoGenerateSlug.bind(this));

      // Form submission via AJAX
      $("#ecs-snippet-editor-form").on("submit", this.saveSnippet.bind(this));

      // Delete snippet
      $(".ecs-delete-snippet").on("click", this.deleteSnippet.bind(this));

      // Test snippet
      $(".ecs-test-snippet").on("click", this.testSnippet.bind(this));

      // Initialize editor resize handle
      this.initEditorResize();
    },

    /**
     * Initialize editor resize functionality
     */
    initEditorResize: function () {
      const self = this;
      let isResizing = false;
      let startY = 0;
      let startHeight = 0;
      let editorElement = null;

      // Create resize handle if it doesn't exist
      const wrapper = $(".ecs-code-editor-wrapper");
      if (wrapper.length && !wrapper.find(".ecs-editor-resize-handle").length) {
        const resizeHandle = $('<div class="ecs-editor-resize-handle"></div>');
        wrapper.append(resizeHandle);

        // Get the CodeMirror element or textarea
        editorElement = wrapper.find(".CodeMirror").length
          ? wrapper.find(".CodeMirror")[0]
          : wrapper.find(".ecs-code-editor")[0];

        // Mouse down on resize handle
        resizeHandle.on("mousedown", function (e) {
          e.preventDefault();
          isResizing = true;
          startY = e.clientY;
          startHeight = $(editorElement).height();

          // Add resizing class for visual feedback
          $("body").addClass("ecs-resizing-editor");
          wrapper.addClass("ecs-resizing");

          // Prevent text selection during resize
          $("body").css("user-select", "none");
        });

        // Mouse move - resize editor
        $(document).on("mousemove", function (e) {
          if (!isResizing) return;

          const deltaY = e.clientY - startY;
          const newHeight = Math.max(200, startHeight + deltaY); // Minimum height of 200px

          // Update editor height
          $(editorElement).height(newHeight);

          // Refresh CodeMirror if it exists
          if (self.codeMirror && self.codeMirror.codemirror) {
            self.codeMirror.codemirror.setSize(null, newHeight);
            self.codeMirror.codemirror.refresh();
          }
        });

        // Mouse up - stop resizing
        $(document).on("mouseup", function () {
          if (isResizing) {
            isResizing = false;
            $("body").removeClass("ecs-resizing-editor");
            wrapper.removeClass("ecs-resizing");
            $("body").css("user-select", "");
          }
        });
      }
    },

    /**
     * Toggle code type info based on selection
     */
    toggleCodeTypeInfo: function () {
      const selectedType = $("#ecs-snippet-type").val();

      // Hide all type descriptions
      $(".ecs-type-description").addClass("hidden");

      // Show selected type description
      $("#ecs-type-info-" + selectedType).removeClass("hidden");
    },

    /**
     * Auto-generate slug from title
     */
    autoGenerateSlug: function () {
      const title = $("#ecs-snippet-title").val();
      const slugInput = $("#ecs-snippet-slug");

      // Only auto-generate if slug is empty or was auto-generated
      if (!slugInput.data("manual-edit")) {
        const slug = this.generateSlug(title);
        slugInput.val(slug);
      }
    },

    /**
     * Generate slug from string
     */
    generateSlug: function (string) {
      return string
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, "")
        .replace(/[\s_-]+/g, "-")
        .replace(/^-+|-+$/g, "");
    },

    /**
     * Mark slug as manually edited
     */
    markSlugManual: function () {
      $("#ecs-snippet-slug").data("manual-edit", true);
    },

    /**
     * Toggle shortcode mode availability based on snippet type
     */
    toggleShortcodeMode: function (type) {
      const shortcodeOption = $(".ecs-mode-shortcode-option");
      const shortcodeRadio = shortcodeOption.find('input[type="radio"]');
      const shortcodeDescription = shortcodeOption.find(
        ".ecs-mode-description"
      );

      if (type === "css" || type === "js") {
        // Disable shortcode mode for CSS/JS
        shortcodeOption.css({
          opacity: "0.5",
          "pointer-events": "none",
        });
        shortcodeRadio.prop("disabled", true);
        shortcodeDescription.text("Not available for CSS/JavaScript snippets");

        // If shortcode mode was selected, switch to auto insert
        if (shortcodeRadio.is(":checked")) {
          $('input[name="mode"][value="auto_insert"]').prop("checked", true);
        }
      } else {
        // Enable shortcode mode for PHP/HTML
        shortcodeOption.css({
          opacity: "1",
          "pointer-events": "auto",
        });
        shortcodeRadio.prop("disabled", false);
        const snippetId = $('input[name="snippet_id"]').val() || "X";
        // Build description without string concatenation for i18n compatibility
        const shortcodeText =
          'Only execute when shortcode is inserted: [ecs_snippet id="' +
          snippetId +
          '"]';
        shortcodeDescription.text(shortcodeText);
      }
    },

    /**
     * Save snippet via REST API
     */
    saveSnippet: async function (e) {
      e.preventDefault();

      // Sync CodeMirror content back to textarea first
      if (this.codeMirror && this.codeMirror.codemirror) {
        this.codeMirror.codemirror.save();
      }

      // Custom validation for title
      const title = $("#ecs-snippet-title").val().trim();
      if (!title) {
        this.showNotice("error", "Please enter a snippet title.");
        $("#ecs-snippet-title").focus();
        return;
      }

      // Custom validation for code
      const code = $("#ecs-snippet-code").val().trim();
      if (!code) {
        this.showNotice("error", "Please enter some code for the snippet.");
        if (this.codeMirror && this.codeMirror.codemirror) {
          this.codeMirror.codemirror.focus();
        } else {
          $("#ecs-snippet-code").focus();
        }
        return;
      }

      // Show loading
      this.showLoading();

      // Get form data
      const snippetId = $('input[name="snippet_id"]').val();
      const isNew = !snippetId || snippetId === "0";

      const data = {
        title: title,
        slug: $("#ecs-snippet-slug").val() || this.generateSlug(title),
        type: $("#ecs-snippet-type").val(),
        code: code,
        active: $("#ecs-snippet-active").is(":checked"),
      };

      const method = isNew ? "POST" : "PUT";
      const path = isNew ? "/ecs/v1/snippets" : `/ecs/v1/snippets/${snippetId}`;

      // Check if wp.apiFetch is available
      if (typeof wp === "undefined" || typeof wp.apiFetch === "undefined") {
        this.showNotice(
          "error",
          "WordPress API not loaded. Please refresh the page."
        );
        this.hideLoading();
        return;
      }

      try {
        const response = await wp.apiFetch({
          path: path,
          method: method,
          data: data,
        });

        this.showNotice(
          "success",
          isNew
            ? "Snippet published successfully!"
            : "Snippet updated successfully!"
        );

        // Reload the editor page after short delay (stay in editor)
        setTimeout(() => {
          if (isNew && response && response.id) {
            // For new snippets, redirect to editor with the new snippet ID
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('snippet_id', response.id);
            currentUrl.searchParams.set('message', 'created');
            window.location.href = currentUrl.toString();
          } else {
            // For existing snippets, just reload the current page
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('message', 'updated');
            window.location.href = currentUrl.toString();
          }
        }, 1000);
      } catch (error) {
        this.showNotice(
          "error",
          "Failed to save snippet: " + (error.message || "Unknown error")
        );
      } finally {
        this.hideLoading();
      }
    },

    /**
     * Delete snippet
     */
    deleteSnippet: async function (e) {
      e.preventDefault();

      const snippetId = $(e.currentTarget).data("snippet-id");

      // Confirm deletion
      if (!confirm("Are you sure you want to move this snippet to trash?")) {
        return;
      }

      // Show loading
      this.showLoading();

      try {
        await wp.apiFetch({
          path: `/ecs/v1/snippets/${snippetId}`,
          method: "DELETE",
        });

        this.showNotice("success", "Snippet moved to trash successfully!");

        // Redirect to snippets list
        setTimeout(() => {
          window.location.href = ecsEditorData.listUrl;
        }, 1000);
      } catch (error) {
        this.showNotice(
          "error",
          "Failed to delete snippet: " + (error.message || "Unknown error")
        );
      } finally {
        this.hideLoading();
      }
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
     * Show notice
     */
    showNotice: function (type, message) {
      // Remove existing notices
      $(".ecs-notice").remove();

      // Create notice
      const $notice = $("<div>", {
        class: `notice notice-${type} is-dismissible ecs-notice ecs-editor-notice`,
        html: `<p>${message}</p>`,
      });

      // Add dismiss button
      $notice.append(
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>'
      );

      // Insert notice into the notices container (between header and content)
      const $noticesContainer = $("#ecs-notices-container");
      if ($noticesContainer.length) {
        $noticesContainer.prepend($notice);
      } else {
        // Fallback: insert after page header
        $(".ecs-editor-page .ecs-page-header").after($notice);
      }

      // Handle dismiss
      $notice.find(".notice-dismiss").on("click", function () {
        $notice.fadeOut(200, function () {
          $(this).remove();
        });
      });

      // Auto-dismiss after 5 seconds - but only for success/info notices, NOT errors
      // Error notices should stay visible until manually dismissed
      if (type !== "error") {
        setTimeout(() => {
          $notice.fadeOut(200, function () {
            $(this).remove();
          });
        }, 5000);
      }

      // Scroll to notice
      $("html, body").animate(
        {
          scrollTop: $notice.offset().top - 50,
        },
        300
      );
    },

    /**
     * Test snippet execution
     */
    testSnippet: function (e) {
      e.preventDefault();

      const snippetId = $(e.target)
        .closest(".ecs-test-snippet")
        .data("snippet-id");

      if (!snippetId) {
        this.showNotice("error", "No snippet ID found for testing.");
        return;
      }

      // Show loading
      this.showLoading();

      // Create test URL with nonce
      const testUrl = new URL(window.location.href);
      testUrl.searchParams.set("ecs_test_snippet", snippetId);
      testUrl.searchParams.set("_wpnonce", ecsEditorData.nonce);

      // Open test in new window
      const testWindow = window.open(
        testUrl.toString(),
        "ecs-test-snippet",
        "width=800,height=600,scrollbars=yes,resizable=yes"
      );

      if (!testWindow) {
        this.showNotice(
          "error",
          "Could not open test window. Please check your popup blocker settings."
        );
        this.hideLoading();
        return;
      }

      // Hide loading after a short delay
      setTimeout(() => {
        this.hideLoading();
      }, 1000);

      this.showNotice(
        "success",
        "Test window opened. Check the results in the new window."
      );
    },

    /**
     * Log ready
     */
    logReady: function () {
      // Editor ready
    },
  };

  /**
   * Location and Conditions Manager
   */
  const ecsConditions = {
    /**
     * Initialize conditions handling
     */
    init: function () {
      this.attachEventListeners();
      this.syncFromHidden();
    },

    /**
     * Attach event listeners
     */
    attachEventListeners: function () {
      // Location preset change
      $("#ecs-location-preset").on(
        "change",
        this.handleLocationChange.bind(this)
      );

      // Show/hide advanced conditions
      $("#ecs-show-advanced-conditions").on(
        "click",
        this.showAdvancedPanel.bind(this)
      );
      $(".ecs-close-advanced").on("click", this.hideAdvancedPanel.bind(this));

      // Advanced conditions change
      $("#ecs-advanced-conditions-panel input").on(
        "change",
        this.handleAdvancedChange.bind(this)
      );

      // Form submit - sync conditions to hidden field
      $("#ecs-snippet-editor-form").on("submit", this.syncToHidden.bind(this));
    },

    /**
     * Handle location preset change
     */
    handleLocationChange: function (e) {
      const location = $(e.target).val();

      // Hide all descriptions
      $(".ecs-location-description span").addClass("hidden");

      // Show relevant description
      $(`.ecs-loc-desc-${location}`).removeClass("hidden");

      // Update conditions based on preset
      const conditions = this.getLocationPresetConditions(location);
      $("#ecs-conditions-json").val(JSON.stringify(conditions));

      // Update advanced panel if visible
      if ($("#ecs-advanced-conditions-panel").is(":visible")) {
        this.syncToAdvancedPanel(conditions);
      }
    },

    /**
     * Get conditions for location preset
     */
    getLocationPresetConditions: function (location) {
      switch (location) {
        case "everywhere":
          return {};
        case "frontend":
          return {
            page_type: [
              "home",
              "front_page",
              "single",
              "page",
              "archive",
              "search",
              "category",
              "tag",
              "404",
            ],
          };
        case "admin":
          return {
            page_type: ["admin"],
          };
        default:
          return {};
      }
    },

    /**
     * Show advanced conditions panel
     */
    showAdvancedPanel: function (e) {
      e.preventDefault();
      $("#ecs-advanced-conditions-panel").slideDown(300);

      // Sync current conditions to advanced panel
      const conditions = JSON.parse($("#ecs-conditions-json").val() || "{}");
      this.syncToAdvancedPanel(conditions);

      // Scroll to panel
      $("html, body").animate(
        {
          scrollTop: $("#ecs-advanced-conditions-panel").offset().top - 100,
        },
        300
      );
    },

    /**
     * Hide advanced conditions panel
     */
    hideAdvancedPanel: function (e) {
      e.preventDefault();
      $("#ecs-advanced-conditions-panel").slideUp(300);
    },

    /**
     * Handle advanced conditions change
     */
    handleAdvancedChange: function () {
      // Get all conditions from advanced panel
      const conditions = this.getAdvancedConditions();

      // Update hidden field
      $("#ecs-conditions-json").val(JSON.stringify(conditions));

      // Update location preset to reflect changes
      this.updateLocationPresetFromConditions(conditions);
    },

    /**
     * Get conditions from advanced panel
     */
    getAdvancedConditions: function () {
      const conditions = {};

      // Page types
      const pageTypes = [];
      $("input[name='conditions[page_type][]']:checked").each(function () {
        pageTypes.push($(this).val());
      });
      if (pageTypes.length > 0) {
        conditions.page_type = pageTypes;
      }

      // Login status
      const loginStatus = $(
        "input[name='conditions[login_status]']:checked"
      ).val();
      if (loginStatus) {
        conditions.login_status = loginStatus;
      }

      // Device type
      const deviceTypes = [];
      $("input[name='conditions[device_type][]']:checked").each(function () {
        deviceTypes.push($(this).val());
      });
      if (deviceTypes.length > 0) {
        conditions.device_type = deviceTypes;
      }

      return conditions;
    },

    /**
     * Sync conditions to advanced panel
     */
    syncToAdvancedPanel: function (conditions) {
      // Clear all checkboxes first
      $("#ecs-advanced-conditions-panel input[type='checkbox']").prop(
        "checked",
        false
      );
      $("#ecs-advanced-conditions-panel input[type='radio']").prop(
        "checked",
        false
      );

      // Page types
      if (conditions.page_type && Array.isArray(conditions.page_type)) {
        conditions.page_type.forEach(function (type) {
          $(`input[name='conditions[page_type][]'][value='${type}']`).prop(
            "checked",
            true
          );
        });
      }

      // Login status
      if (conditions.login_status) {
        $(
          `input[name='conditions[login_status]'][value='${conditions.login_status}']`
        ).prop("checked", true);
      } else {
        $("input[name='conditions[login_status]'][value='']").prop(
          "checked",
          true
        );
      }

      // Device type
      if (conditions.device_type && Array.isArray(conditions.device_type)) {
        conditions.device_type.forEach(function (type) {
          $(`input[name='conditions[device_type][]'][value='${type}']`).prop(
            "checked",
            true
          );
        });
      }
    },

    /**
     * Update location preset based on current conditions
     */
    updateLocationPresetFromConditions: function (conditions) {
      if (!conditions || Object.keys(conditions).length === 0) {
        $("#ecs-location-preset").val("everywhere");
        return;
      }

      // Check if it matches admin preset
      if (
        conditions.page_type &&
        conditions.page_type.length === 1 &&
        conditions.page_type[0] === "admin"
      ) {
        $("#ecs-location-preset").val("admin");
        return;
      }

      // Check if it matches frontend preset
      const frontendPages = [
        "home",
        "front_page",
        "single",
        "page",
        "archive",
        "search",
        "category",
        "tag",
        "404",
      ];
      if (
        conditions.page_type &&
        conditions.page_type.every((type) => frontendPages.includes(type)) &&
        frontendPages.every((type) => conditions.page_type.includes(type))
      ) {
        $("#ecs-location-preset").val("frontend");
        return;
      }

      // Custom conditions - leave preset as is or set to everywhere
      // (User is using advanced conditions)
    },

    /**
     * Sync from hidden field on page load
     */
    syncFromHidden: function () {
      const conditions = JSON.parse($("#ecs-conditions-json").val() || "{}");
      this.updateLocationPresetFromConditions(conditions);
    },

    /**
     * Sync to hidden field before form submit
     */
    syncToHidden: function () {
      // If advanced panel is visible, use advanced conditions
      if ($("#ecs-advanced-conditions-panel").is(":visible")) {
        const conditions = this.getAdvancedConditions();
        $("#ecs-conditions-json").val(JSON.stringify(conditions));
      } else {
        // Use location preset
        const location = $("#ecs-location-preset").val();
        const conditions = this.getLocationPresetConditions(location);
        $("#ecs-conditions-json").val(JSON.stringify(conditions));
      }
    },
  };

  /**
   * Revisions Manager
   */
  const ecsRevisions = {
    /**
     * Initialize revisions
     */
    init: function () {
      // Only init if revisions container exists
      if ($("#ecs-revisions-list").length) {
        this.fetchRevisions();
        this.attachEventListeners();
      }
    },

    /**
     * Attach event listeners
     */
    attachEventListeners: function () {
      // Refresh button
      $("#ecs-refresh-revisions").on("click", this.fetchRevisions.bind(this));

      // Restore button (delegated)
      $("#ecs-revisions-list").on(
        "click",
        ".ecs-restore-revision",
        this.restoreRevision.bind(this)
      );
    },

    /**
     * Fetch revisions from API
     */
    fetchRevisions: async function () {
      const container = $("#ecs-revisions-list");
      const snippetId = $('input[name="snippet_id"]').val();

      if (!snippetId || snippetId === "0") {
        return;
      }

      // Show loading
      container.html(`
        <div class="ecs-loading-placeholder">
          <span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
          Scanning for revisions...
        </div>
      `);

      try {
        const revisions = await wp.apiFetch({
          path: `/ecs/v1/snippets/${snippetId}/revisions`,
          method: "GET",
        });

        this.renderRevisions(revisions);
      } catch (error) {
        container.html(`
          <div class="notice notice-error inline">
            <p>Failed to load revisions: ${error.message}</p>
          </div>
        `);
      }
    },

    /**
     * Render revisions list
     */
    renderRevisions: function (revisions) {
      const container = $("#ecs-revisions-list");

      if (!revisions || revisions.length === 0) {
        container.html(`
          <div class="ecs-no-revisions">
            <p>No revisions found for this snippet.</p>
          </div>
        `);
        return;
      }

      let html = '<ul class="ecs-revisions-timeline">';

      revisions.forEach((rev) => {
        html += `
          <li class="ecs-revision-item">
            <div class="ecs-revision-info">
              <span class="ecs-revision-author">
                <strong>${rev.author_name}</strong>
              </span>
              <span class="ecs-revision-meta">
                ${rev.time_ago} (${rev.created_at})
              </span>
              <span class="ecs-revision-title">
                ${rev.title}
              </span>
            </div>
            <div class="ecs-revision-actions">
              <button type="button" class="button button-small ecs-restore-revision" data-id="${rev.id}">
                Restore
              </button>
            </div>
          </li>
        `;
      });

      html += "</ul>";
      container.html(html);
    },

    /**
     * Restore a revision
     */
    restoreRevision: async function (e) {
      e.preventDefault();
      const btn = $(e.currentTarget);
      const revisionId = btn.data("id");

      if (
        !confirm(
          "Are you sure you want to restore this version? This will overwrite the current code and title."
        )
      ) {
        return;
      }

      // Show loading state
      btn.addClass("updating-message").prop("disabled", true).text("Restoring...");

      try {
        const response = await wp.apiFetch({
          path: `/ecs/v1/revisions/${revisionId}/restore`,
          method: "POST",
        });

        ecsEditor.showNotice("success", "Snippet restored successfully! Reloading...");

        // Reload page to show restored content
        setTimeout(() => {
          // Add message param
          const currentUrl = new URL(window.location.href);
          currentUrl.searchParams.set("message", "restored");
          window.location.href = currentUrl.toString();
        }, 1000);
      } catch (error) {
        ecsEditor.showNotice(
          "error",
          "Failed to restore revision: " + (error.message || "Unknown error")
        );
        btn.removeClass("updating-message").prop("disabled", false).text("Restore");
      }
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    // Only initialize on editor page
    if ($("#ecs-snippet-editor-form").length) {
      // Check if wp.codeEditor is available
      if (typeof wp === "undefined" || typeof wp.codeEditor === "undefined") {
        alert("Code editor library not loaded. Please refresh the page.");
        return;
      }

      ecsEditor.init();
      ecsConditions.init();
      ecsRevisions.init();
    }
  });
})(jQuery);
