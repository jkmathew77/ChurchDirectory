# Deploying Community Directory to Bluehost

## Prerequisites
- SSH access to your Bluehost account
- Git installed on the server (Bluehost has it by default)

## First-Time Setup

### 1. SSH into Bluehost
```bash
ssh yourusername@yourdomain.com
```

### 2. Clone the repository
```bash
cd ~
git clone https://github.com/jkmathew77/ChurchDirectory.git
```

### 3. Run the deploy script
```bash
cd ~/ChurchDirectory
bash deploy.sh ~/public_html
```

This creates a symlink from your WordPress plugins directory to the plugin source code. You should see output like:
```
[OK] Symlink created:
    /home/username/public_html/wp-content/plugins/community-directory -> /home/username/ChurchDirectory/plugin/community-directory
[OK] Plugin version: 0.2.1
```

### 4. Activate the plugin
Go to **WP Admin > Plugins** and click **Activate** on "Community Directory".

## Updating the Plugin

After pushing new code to GitHub, SSH in and run:

```bash
cd ~/ChurchDirectory
bash deploy.sh
```

That's it. The script runs `git pull` and the symlink ensures WordPress immediately sees the new files.

## Rolling Back

If an update breaks something:

```bash
cd ~/ChurchDirectory
bash deploy.sh --rollback
```

This shows recent commits. Pick one to roll back to:
```bash
git checkout abc1234
```

To return to the latest version:
```bash
git checkout main && git pull
```

## How It Works

Instead of uploading ZIP files through WordPress, the plugin directory is a **symlink** that points directly to the git repository:

```
wp-content/plugins/community-directory  -->  ~/ChurchDirectory/plugin/community-directory
```

When you `git pull`, the files update in place. No ZIPs, no uploads, no duplicate folders.

## Troubleshooting

**"Plugin file does not exist" error:**
The symlink may have been broken. Re-run setup:
```bash
cd ~/ChurchDirectory && bash deploy.sh ~/public_html
```

**Plugin not showing in WP Admin:**
Check that the symlink exists:
```bash
ls -la ~/public_html/wp-content/plugins/community-directory
```

**Permission errors:**
Ensure the repo files are readable by the web server:
```bash
chmod -R 755 ~/ChurchDirectory/plugin/community-directory
```
