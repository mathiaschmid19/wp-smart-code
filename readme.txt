=== WP Smart Code ===
Contributors: amineouhannou
Tags: code snippets, php, javascript, css, html, custom code, ai coding, artificial intelligence
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely manage, execute, and generate PHP, JavaScript, CSS, and HTML code snippets with AI assistance in WordPress.

== Description ==

**WP Smart Code** is a modern, secure WordPress plugin that revolutionizes how you add custom code to your website. With built-in **AI intelligence**, you can now generate, improve, and explain code snippets instantly without leaving your dashboard.

Say goodbye to editing `functions.php` or risking site crashes. WP Smart Code provides a safe, sandboxed environment for your custom code.

= 🤖 New: AI-Powered Coding Assistant =

*   **Generate Code:** Describe what you need in plain English, and our AI will write the code for you (PHP, JS, CSS, or HTML).
*   **Improve Code:** Have existing code? The AI can optimize it for performance, security, and readability.
*   **Explain Code:** Don't understand a snippet? The AI will explain exactly what it does line-by-line.

= ✨ Key Features =

*   **AI Coding Assistant** – Generate, improve, and understand code instantly.
*   **Revision System** – Automatically track changes with a timeline view. Compare versions side-by-side and restore with one click.
*   **Safe Execution Sandbox** – PHP code runs in a secure sandbox that blocks dangerous functions to keep your site safe.
*   **Smart Syntax Highlighting** – Professional editor for PHP, JavaScript, CSS, and HTML.
*   **Auto-Insertion** – Automatically run snippets in the Header, Footer, or Body.
*   **Shortcode Support** – Place snippets anywhere using `[ecs_snippet id="123"]`.
*   **Error Protection** – Automatically deactivates snippets that cause fatal errors.
*   **Import/Export** – Easily backup and migrate your snippets between sites.

= 🛡️ Security First =

Security is our top priority. WP Smart Code includes:
*   **PHP Sandboxing:** Blocks dangerous functions like `exec()`, `shell_exec()`, and `system()`.
*   **Syntax Validation:** Checks code for errors before saving to prevent site crashes.
*   **Role-Based Access:** Only administrators with `manage_options` capability can manage snippets.
*   **Nonce Protection:** Full CSRF protection on all forms and actions.

= Perfect For =

*   **Beginners:** Use AI to write code for you—no coding knowledge required!
*   **Developers:** Speed up your workflow with AI generation and code optimization.
*   **Agencies:** Manage custom functionality safely across client sites with revisions and backups.

== Installation ==

= Automatic Installation =

1.  Log in to your WordPress admin panel
2.  Go to **Plugins > Add New**
3.  Search for "WP Smart Code"
4.  Click **Install Now** and then **Activate**

= Manual Installation =

1.  Download the plugin zip file
2.  Go to **Plugins > Add New > Upload Plugin**
3.  Choose the zip file and click **Install Now**
4.  Activate the plugin

= After Installation =

1.  Navigate to **WP Smart Code** in your dashboard
2.  Click **Add New** to create a snippet
3.  Try the **AI Assistant** button to generate your first code!

== Frequently Asked Questions ==

= How does the AI Assistant work? =
The AI Assistant connects to sophisticated language models to understand your request and generate working WordPress code. It can create PHP functions, CSS styles, JavaScript interactions, and HTML structures.

= Is the AI feature free? =
The plugin comes with core AI capabilities. Depending on usage volume, you may need an API key for extensive generation.

= key feature: Revisions system = 
Every time you save a snippet, a revision is created. You can view the history, compare changes side-by-side, and restore previous versions if something breaks.

= Will my snippets work if I change themes? =
Yes! Unlike `functions.php` edits which are tied to your valid theme, WP Smart Code snippets are stored in the database and persist regardless of which theme you use.

= Can I use this for Google Analytics? =
Absolutely. Just create a "JavaScript" snippet, paste your tracking code, and select "Run in Header" (Auto Insert).

== Screenshots ==

1.  **Snippet Editor with AI** - Modern editor featuring the AI Assistant button.
2.  **AI Code Generator** - Generate code by describing what you want.
3.  **Revision Timeline** - Visually compare and restore previous versions.
4.  **Snippet Library** - Manage all your custom code in one place.

== Changelog ==

= 1.0.5 =
*   **New:** AI Coding Assistant (Generate, Improve, Explain code)
*   **New:** Revision System with diff comparison and restore
*   **New:** PHP Sandbox for safer execution
*   **New:** "Skip Syntax Check" option for edge cases
*   Improved shortcode security with HTML sanitization
*   Updated admin UI for better user experience

= 1.0.1 =
*   Fixed CSS styling issues
*   Enhanced security measures

= 1.0.0 =
*   Initial release
*   Support for PHP, JS, CSS, HTML
*   Syntax highlighting
*   Import/Export

== Upgrade Notice ==

= 1.0.5 =
Major update! Now featuring an AI Assistant to write code for you, plus a full Revision system to track changes.

== Privacy ==

WP Smart Code stores snippets locally. AI requests are sent securely to the processing API but your site data remains private. We do not track personal information.