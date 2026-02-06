/**
 * AI Assistant JavaScript for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * AI Assistant class.
   */
  class AIAssistant {
    constructor() {
      this.isGenerating = false;
      this.waitTimer = null;
      this.init();
    }

    /**
     * Initialize AI Assistant.
     */
    init() {
      this.createAIPanel();
      this.bindEvents();
      this.setupToggle();
    }

    /**
     * Create AI panel in editor.
     */
    createAIPanel() {
      const $editorMain = $(".ecs-editor-content");

      if ($editorMain.length === 0) {
        return;
      }

      // Check if AI panel already exists
      if ($("#ecs-ai-panel").length > 0) {
        return;
      }

      // Find the code editor card to insert before it
      const $codeCard = $(".ecs-card-code");

      const aiPanel = `
                <div class="ecs-card ecs-ai-panel" id="ecs-ai-panel">
                    <div class="ecs-card-header">
                        <div class="ecs-ai-header-content">
                            <h3 class="ecs-card-title">🤖 AI Code Assistant</h3>
                            <div class="ecs-ai-status">
                                <span class="ecs-ai-indicator ready" id="ecs-ai-status">Ready</span>
                            </div>
                        </div>
                    </div>
                    <div class="ecs-card-content">
                        <div class="ecs-ai-tabs">
                            <button class="ecs-ai-tab active" data-tab="generate">
                                <span class="ecs-tab-icon">✨</span>
                                <span class="ecs-tab-label">Generate</span>
                            </button>
                            <button class="ecs-ai-tab" data-tab="improve">
                                <span class="ecs-tab-icon">🚀</span>
                                <span class="ecs-tab-label">Improve</span>
                            </button>
                            <button class="ecs-ai-tab" data-tab="explain">
                                <span class="ecs-tab-icon">📖</span>
                                <span class="ecs-tab-label">Explain</span>
                            </button>
                        </div>
                        
                        <div class="ecs-ai-content">
                            <!-- Generate Tab -->
                            <div class="ecs-ai-tab-content active" id="ecs-tab-generate">
                                <div class="ecs-ai-prompt-section">
                                    <label for="ecs-ai-prompt" class="ecs-form-label">
                                        <strong>Describe what you want to create:</strong>
                                    </label>
                                    <textarea 
                                        id="ecs-ai-prompt" 
                                        class="ecs-ai-textarea" 
                                        placeholder="Example: Add a duplicate button to WordPress posts and pages..."
                                        rows="4"
                                    ></textarea>
                                    
                                    <!-- Quick Action Buttons -->
                                    <div class="ecs-ai-quick-actions">
                                        <span class="ecs-quick-label">Quick Actions:</span>
                                        <button type="button" class="ecs-quick-btn" data-prompt="Add a custom admin menu page with settings">Admin Menu</button>
                                        <button type="button" class="ecs-quick-btn" data-prompt="Create a custom widget for the sidebar">Custom Widget</button>
                                        <button type="button" class="ecs-quick-btn" data-prompt="Add custom CSS to change the website appearance">Custom Styling</button>
                                        <button type="button" class="ecs-quick-btn" data-prompt="Add JavaScript for smooth scrolling animations">Smooth Scroll</button>
                                    </div>
                                    
                                    <div class="ecs-ai-actions">
                                        <button type="button" class="button button-primary" id="ecs-generate-code">
                                            <span class="ecs-btn-icon">✨</span> Generate Code
                                        </button>
                                        <button type="button" class="button button-secondary" id="ecs-clear-prompt">
                                            Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Improve Tab -->
                            <div class="ecs-ai-tab-content" id="ecs-tab-improve">
                                <div class="ecs-ai-improve-section">
                                    <label class="ecs-form-label">
                                        <strong>Select improvement type:</strong>
                                    </label>
                                    <div class="ecs-improvement-grid">
                                        <label class="ecs-improvement-card">
                                            <input type="radio" name="improvement_type" value="general" checked>
                                            <div class="ecs-improvement-content">
                                                <span class="ecs-improvement-icon">✨</span>
                                                <span class="ecs-improvement-title">General</span>
                                                <span class="ecs-improvement-desc">Overall code improvements</span>
                                            </div>
                                        </label>
                                        <label class="ecs-improvement-card">
                                            <input type="radio" name="improvement_type" value="security">
                                            <div class="ecs-improvement-content">
                                                <span class="ecs-improvement-icon">🔒</span>
                                                <span class="ecs-improvement-title">Security</span>
                                                <span class="ecs-improvement-desc">Enhance security measures</span>
                                            </div>
                                        </label>
                                        <label class="ecs-improvement-card">
                                            <input type="radio" name="improvement_type" value="performance">
                                            <div class="ecs-improvement-content">
                                                <span class="ecs-improvement-icon">⚡</span>
                                                <span class="ecs-improvement-title">Performance</span>
                                                <span class="ecs-improvement-desc">Optimize for speed</span>
                                            </div>
                                        </label>
                                        <label class="ecs-improvement-card">
                                            <input type="radio" name="improvement_type" value="readability">
                                            <div class="ecs-improvement-content">
                                                <span class="ecs-improvement-icon">📝</span>
                                                <span class="ecs-improvement-title">Readability</span>
                                                <span class="ecs-improvement-desc">Clean & clear code</span>
                                            </div>
                                        </label>
                                        <label class="ecs-improvement-card">
                                            <input type="radio" name="improvement_type" value="error_handling">
                                            <div class="ecs-improvement-content">
                                                <span class="ecs-improvement-icon">🛡️</span>
                                                <span class="ecs-improvement-title">Error Handling</span>
                                                <span class="ecs-improvement-desc">Better error management</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="ecs-ai-actions">
                                        <button type="button" class="button button-primary" id="ecs-improve-code">
                                            <span class="ecs-btn-icon">🚀</span> Improve Code
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Explain Tab -->
                            <div class="ecs-ai-tab-content" id="ecs-tab-explain">
                                <div class="ecs-ai-explain-section">
                                    <div class="ecs-explain-info">
                                        <span class="ecs-explain-icon">📖</span>
                                        <div class="ecs-explain-text">
                                            <strong>Get Code Explanation</strong>
                                            <p>AI will analyze your code and provide a detailed explanation of how it works, what it does, and any potential improvements.</p>
                                        </div>
                                    </div>
                                    <div class="ecs-ai-actions">
                                        <button type="button" class="button button-primary" id="ecs-explain-code">
                                            <span class="ecs-btn-icon">📖</span> Explain Code
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ecs-ai-results" id="ecs-ai-results" style="display: none;">
                            <div class="ecs-ai-result-header">
                                <h4><span class="ecs-result-icon">💡</span> AI Response</h4>
                                <button type="button" class="button button-small" id="ecs-close-results">
                                    ✕ Close
                                </button>
                            </div>
                            <div class="ecs-ai-result-content" id="ecs-ai-result-content">
                                <!-- AI response will be inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
            `;

      // Insert the AI panel before the code editor card
      if ($codeCard.length > 0) {
        $codeCard.before(aiPanel);
      } else {
        $editorMain.append(aiPanel);
      }
    }

    /**
     * Setup toggle functionality.
     */
    setupToggle() {
      $(document).on("click", "#ecs-toggle-ai-assistant", (e) => {
        e.preventDefault();
        this.toggleAIPanel();
      });
    }

    /**
     * Toggle AI panel visibility.
     */
    toggleAIPanel() {
      const $panel = $("#ecs-ai-panel");
      const $button = $("#ecs-toggle-ai-assistant");

      if ($panel.hasClass("show")) {
        $panel.removeClass("show");
        $button.removeClass("active");
        $button.html("🤖 AI Assistant");
      } else {
        $panel.addClass("show");
        $button.addClass("active");
        $button.html("🤖 Hide AI");
      }
    }

    /**
     * Bind event handlers.
     */
    bindEvents() {
      // Tab switching
      $(document).on("click", ".ecs-ai-tab", this.handleTabSwitch.bind(this));

      // Generate code
      $(document).on(
        "click",
        "#ecs-generate-code",
        this.handleGenerateCode.bind(this)
      );

      // Quick action buttons
      $(document).on(
        "click",
        ".ecs-quick-btn",
        this.handleQuickAction.bind(this)
      );

      // Improve code
      $(document).on(
        "click",
        "#ecs-improve-code",
        this.handleImproveCode.bind(this)
      );

      // Explain code
      $(document).on(
        "click",
        "#ecs-explain-code",
        this.handleExplainCode.bind(this)
      );

      // Clear prompt
      $(document).on("click", "#ecs-clear-prompt", this.clearPrompt.bind(this));

      // Close results
      $(document).on(
        "click",
        "#ecs-close-results",
        this.closeResults.bind(this)
      );

      // Enter key in prompt
      $(document).on(
        "keydown",
        "#ecs-ai-prompt",
        this.handlePromptKeydown.bind(this)
      );
    }

    /**
     * Handle tab switching.
     */
    handleTabSwitch(e) {
      e.preventDefault();

      const $tab = $(e.currentTarget);
      const tabName = $tab.data("tab");

      // Update tab buttons
      $(".ecs-ai-tab").removeClass("active");
      $tab.addClass("active");

      // Update tab content
      $(".ecs-ai-tab-content").removeClass("active");
      $(`#ecs-tab-${tabName}`).addClass("active");

      // Close results when switching tabs
      this.closeResults();
    }

    /**
     * Handle generate code.
     */
    handleGenerateCode(e) {
      e.preventDefault();

      const prompt = $("#ecs-ai-prompt").val().trim();
      if (!prompt) {
        this.showError(
          "Please enter a description of what you want to create."
        );
        return;
      }

      const type = $("#ecs-snippet-type").val();
      this.generateCode(prompt, type);
    }

    /**
     * Handle quick action button click.
     */
    handleQuickAction(e) {
      e.preventDefault();
      const prompt = $(e.currentTarget).data("prompt");
      $("#ecs-ai-prompt").val(prompt);
    }

    /**
     * Handle improve code.
     */
    handleImproveCode(e) {
      e.preventDefault();

      const code = $("#ecs-snippet-code").val().trim();
      if (!code) {
        this.showError("Please enter some code to improve.");
        return;
      }

      const type = $("#ecs-snippet-type").val();
      const improvementType =
        $('input[name="improvement_type"]:checked').val() || "general";
      this.improveCode(code, type, improvementType);
    }

    /**
     * Handle explain code.
     */
    handleExplainCode(e) {
      e.preventDefault();

      const code = $("#ecs-snippet-code").val().trim();
      if (!code) {
        this.showError("Please enter some code to explain.");
        return;
      }

      const type = $("#ecs-snippet-type").val();
      this.explainCode(code, type);
    }

    /**
     * Clear prompt.
     */
    clearPrompt() {
      $("#ecs-ai-prompt").val("");
    }

    /**
     * Close results panel.
     */
    closeResults() {
      $("#ecs-ai-results").hide();
    }

    /**
     * Handle prompt keydown.
     */
    handlePromptKeydown(e) {
      if (e.ctrlKey && e.key === "Enter") {
        e.preventDefault();
        $("#ecs-generate-code").click();
      }
    }

    /**
     * Generate code via AI.
     */
    generateCode(prompt, type) {
      this.setStatus("Generating code...", "working");
      this.setGenerating(true);
      this.startWaitingTicker();

      $.post(ajaxurl, {
        action: "ecs_ai_generate_code",
        prompt: prompt,
        type: type,
        nonce: ecsAiData.nonce,
      })
        .done((response) => {
          this.stopWaitingTicker();
          if (response.success) {
            const payload = response.data || {};
            const code = payload.code || payload.raw || "";
            this.showResults(code, "Generated Code");
            if (payload.code) this.insertCode(payload.code);
          } else {
            this.showError(response.data || "Failed to generate code");
          }
        })
        .fail(() => {
          this.stopWaitingTicker();
          this.showError("Network error. Please try again.");
        })
        .always(() => {
          this.setGenerating(false);
          this.setStatus("Ready", "ready");
        });
    }

    /**
     * Improve code via AI.
     */
    improveCode(code, type, improvementType) {
      this.setStatus("Improving code...", "working");
      this.setGenerating(true);
      this.startWaitingTicker();

      $.post(ajaxurl, {
        action: "ecs_ai_improve_code",
        code: code,
        type: type,
        improvement: improvementType,
        nonce: ecsAiData.nonce,
      })
        .done((response) => {
          this.stopWaitingTicker();
          if (response.success) {
            const payload = response.data || {};
            const code = payload.code || payload.raw || "";
            this.showResults(code, "Improved Code", payload.changes);
            if (payload.code) this.insertCode(payload.code);
          } else {
            this.showError(response.data || "Failed to improve code");
          }
        })
        .fail(() => {
          this.stopWaitingTicker();
          this.showError("Network error. Please try again.");
        })
        .always(() => {
          this.setGenerating(false);
          this.setStatus("Ready", "ready");
        });
    }

    /**
     * Explain code via AI.
     */
    explainCode(code, type) {
      this.setStatus("Analyzing code...", "working");
      this.setGenerating(true);
      this.startWaitingTicker();

      $.post(ajaxurl, {
        action: "ecs_ai_explain_code",
        code: code,
        type: type,
        nonce: ecsAiData.nonce,
      })
        .done((response) => {
          this.stopWaitingTicker();
          if (response.success) {
            const payload = response.data || {};
            const text = payload.explanation || payload.raw || "";
            this.showResults(text, "Code Explanation");
          } else {
            this.showError(response.data || "Failed to explain code");
          }
        })
        .fail(() => {
          this.stopWaitingTicker();
          this.showError("Network error. Please try again.");
        })
        .always(() => {
          this.setGenerating(false);
          this.setStatus("Ready", "ready");
        });
    }

    /**
     * Show results panel.
     */
    showResults(content, title, changes = null) {
      let html = `<div class="ecs-ai-result-header">
        <h4>${title}</h4>
        <div class="ecs-ai-result-toolbar">
          <button type="button" class="button button-small" id="ecs-ai-copy">Copy</button>
          <button type="button" class="button button-small" id="ecs-ai-expand">Expand</button>
        </div>
      </div>`;

      if (changes) {
        html += `<div class="ecs-ai-changes"><h5>Changes Made:</h5><p>${changes}</p></div>`;
      }

      html += `<div class="ecs-ai-code-block" id="ecs-ai-code-container">
        <pre><code id="ecs-ai-code-content">${this.escapeHtml(
          content
        )}</code></pre>
      </div>`;

      $("#ecs-ai-result-content").html(html);
      $("#ecs-ai-results").show();

      // Bind toolbar actions
      this.bindResultActions(content);
    }

    /**
     * Bind actions for result toolbar buttons.
     */
    bindResultActions(content) {
      // Copy button
      $(document)
        .off("click", "#ecs-ai-copy")
        .on("click", "#ecs-ai-copy", () => {
          this.copyToClipboard(content);
        });

      // Expand/Collapse button
      $(document)
        .off("click", "#ecs-ai-expand")
        .on("click", "#ecs-ai-expand", () => {
          this.toggleCodeExpansion();
        });
    }

    /**
     * Copy text to clipboard.
     */
    copyToClipboard(text) {
      if (navigator.clipboard && window.isSecureContext) {
        // Use modern clipboard API
        navigator.clipboard
          .writeText(text)
          .then(() => {
            this.showCopySuccess();
          })
          .catch(() => {
            this.fallbackCopyToClipboard(text);
          });
      } else {
        // Fallback for older browsers
        this.fallbackCopyToClipboard(text);
      }
    }

    /**
     * Fallback copy method for older browsers.
     */
    fallbackCopyToClipboard(text) {
      const textArea = document.createElement("textarea");
      textArea.value = text;
      textArea.style.position = "fixed";
      textArea.style.left = "-999999px";
      textArea.style.top = "-999999px";
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();

      try {
        document.execCommand("copy");
        this.showCopySuccess();
      } catch (err) {
        alert("Failed to copy to clipboard");
      }

      document.body.removeChild(textArea);
    }

    /**
     * Show copy success feedback.
     */
    showCopySuccess() {
      const $button = $("#ecs-ai-copy");
      const originalText = $button.text();
      $button.text("Copied!").addClass("button-secondary");

      setTimeout(() => {
        $button.text(originalText).removeClass("button-secondary");
      }, 2000);
    }

    /**
     * Toggle code expansion.
     */
    toggleCodeExpansion() {
      const $container = $("#ecs-ai-code-container");
      const $button = $("#ecs-ai-expand");

      if ($container.hasClass("expanded")) {
        $container.removeClass("expanded");
        $button.text("Expand");
      } else {
        $container.addClass("expanded");
        $button.text("Collapse");
      }
    }

    // Simple waiting ticker
    startWaitingTicker() {
      let dots = 0;
      if (this.waitTimer) clearInterval(this.waitTimer);
      this.waitTimer = setInterval(() => {
        dots = (dots + 1) % 4;
        // Update status text instead of streaming target
        this.setStatus("Generating" + ".".repeat(dots), "working");
      }, 400);
    }

    stopWaitingTicker() {
      if (this.waitTimer) {
        clearInterval(this.waitTimer);
        this.waitTimer = null;
      }
    }

    /**
     * Show error message.
     */
    showError(message) {
      this.setStatus(message, "error");

      // Show error in results panel for better visibility
      let html = `<h4>Error</h4>`;
      html += `<div class="ecs-ai-error-message">`;
      html += `<p><strong>❌ ${message}</strong></p>`;
      html += `<p>Please check your API key in the AI Assistant settings page.</p>`;
      html += `</div>`;

      $("#ecs-ai-result-content").html(html);
      $("#ecs-ai-results").show();

      setTimeout(() => {
        this.setStatus("Ready", "ready");
      }, 5000);
    }

    /**
     * Insert code into editor.
     */
    insertCode(code) {
      $("#ecs-snippet-code").val(code);

      // Trigger change event for any listeners
      $("#ecs-snippet-code").trigger("change");
    }

    /**
     * Set AI status.
     */
    setStatus(message, type) {
      const $status = $("#ecs-ai-status");
      $status.text(message).removeClass("ready working error").addClass(type);
    }

    /**
     * Set generating state.
     */
    setGenerating(generating) {
      this.isGenerating = generating;
      $(".ecs-ai-actions button").prop("disabled", generating);

      if (generating) {
        $(".ecs-ai-actions button").addClass("loading");
      } else {
        $(".ecs-ai-actions button").removeClass("loading");
      }
    }

    /**
     * Escape HTML for display.
     */
    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }
  }

  // Initialize AI Assistant when document is ready
  $(document).ready(() => {
    new AIAssistant();
  });
})(jQuery);
