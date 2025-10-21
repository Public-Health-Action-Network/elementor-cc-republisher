# Elementor CC Republisher Widget

A lightweight Elementor widget that integrates the [Creative Commons Post Republisher](https://github.com/creativecommons/Creative-Commons-Post-Republisher) block directly into Elementor Single Post templates.  
It allows creators to display the **“Republish”** license badge and modal on any post template while keeping full Creative Commons compliance.

---

## 🧩 Features

- ✅ Adds a **CC Republisher** widget to Elementor  
- ✅ Fully license-aware — pulls from post meta or site defaults  
- ✅ Works in **Elementor Single templates** (static render)  
- ✅ Includes a **centered modal** with a single close button  
- ✅ Optional **debug panel** (for admins or editor view)  
- ✅ Optional **GitHub auto-updater** (toggle in Settings)  
- ✅ Safe loading — no fatal errors if Elementor is missing

---

## 📦 Installation

### 1. Manual installation
1. Download or clone this repository:

   ```bash
   git clone https://github.com/Public-Health-Action-Network/elementor-cc-republisher.git
   ```

2. Upload the folder to your WordPress installation:

   ```
   /wp-content/plugins/elementor-cc-republisher/
   ```

3. Activate **Elementor CC Republisher Widget** from your WordPress **Plugins → Installed Plugins** page.

4. Ensure the **Creative Commons Post Republisher** plugin is also active.

---

### 2. Install via ZIP
1. Download the latest release ZIP from the **[Releases](../../releases)** page or click **Code → Download ZIP**.  
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**.  
3. Upload the ZIP, install, and activate it.

---

## ⚙️ Settings & Configuration

### Elementor Widget
After activation, you’ll see a new Elementor widget named **CC Republisher** under the *General* section.

You can:
- Render static license-aware markup (auto-detected from post/license meta)
- Override markup with your own block comment (`<!-- wp:cc/post-republisher -->`)
- Show debug info in editor or frontend (admins only)
- Control visibility (only when a license is set)

---

### Plugin Settings (GitHub Updater)
The plugin adds a **Settings → CC Republisher** screen where you can toggle the GitHub updater.

| Option | Description |
|:-------|:-------------|
| **Enable GitHub auto-updates** | When checked, WordPress will check the PHAN GitHub repo for new versions and show them in the standard Updates screen. |

**Note:** The updater is completely opt-in and runs only for admins.  
It checks every 6 hours and compares the version header from the main branch.

---

## 🔄 Updating

### Automatic (Recommended)
- Enable **“GitHub auto-updates”** in **Settings → CC Republisher**
- Go to **Dashboard → Updates → Check again**
- If a newer version exists in this repository’s main branch, WordPress will show an update notice

### Manual
- Download the latest ZIP from this GitHub repo
- Replace the existing folder in `/wp-content/plugins/elementor-cc-republisher/`
- Reactivate the plugin if needed

---

## 🧠 Technical Notes

- Widget class: `Elementor_CC_Republisher_Widget`
- Widget loads from `/widgets/class-elementor-cc-republisher-widget.php`
- Main plugin file: `/elementor-cc-republisher.php`
- Safe loader prevents Elementor dependency errors
- Uses post meta keys like:
  - `cc_post_republisher_license`
  - `cc_license_choice`
  - `ccpr_license`
- Falls back to global options in `cc_post_republisher_settings`
- Modal auto-initializes if `CCPostRepublisher.init()` is available

---

## 🧰 Developer Notes

To enable the GitHub updater manually (bypassing settings page):

Add this line to your `wp-config.php` before the “That’s all, stop editing!” comment:

```php
define( 'ECCR_ENABLE_UPDATER', true );
```

Or via a filter in your theme or mu-plugin:

```php
add_filter( 'eccr_enable_updater', '__return_true' );
```

---

## 🧑‍💻 Contributing

Pull requests are welcome!  
Please ensure your changes:
- Follow WordPress coding standards
- Include inline documentation where applicable
- Preserve backward compatibility with existing Elementor widget versions

---

## 🏷️ License

This plugin integrates the [Creative Commons Post Republisher](https://github.com/creativecommons/Creative-Commons-Post-Republisher), which is distributed under the **GNU General Public License v2.0 or later**.

You are free to:
- Use, modify, and redistribute under GPLv2+ terms
- Attribute appropriately in derivative works

---

## 🌐 Repository Information

- **Plugin slug:** `elementor-cc-republisher`
- **Author:** [Public Health Action Network (PHAN)](https://phan.global)
- **Repository:** [Public-Health-Action-Network/Wordpress/elementor-cc-republisher](https://github.com/Public-Health-Action-Network/elementor-cc-republisher)
- **Minimum WordPress version:** 5.8
- **Tested up to:** 6.7
- **Requires PHP:** 7.4+

---

## 🧾 Changelog

### 1.1.0
- Added **Settings → CC Republisher** page  
- Added opt-in **GitHub auto-updater**  
- Added **plugin row meta** with version and updater status  
- Improved modal layout and close-button behavior  
- Added full inline documentation and developer comments  
- Improved Elementor loader for reliability

---

© Public Health Action Network (PHAN).  
Clean Air is a Human Right 🌍
