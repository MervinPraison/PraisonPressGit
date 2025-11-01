# GitHub OAuth App Setup Guide

This guide explains how to set up your own GitHub OAuth App for the PraisonPress plugin.

## Why Do I Need My Own GitHub OAuth App?

Each WordPress site using PraisonPress needs its own GitHub OAuth App because:

1. **Unique Redirect URL**: The OAuth callback URL must match your site's domain
2. **Security**: Each site should have its own Client ID and Client Secret
3. **Repository Access**: The OAuth app needs permission to access your content repository

---

## Step-by-Step Setup

### 1. Create a GitHub OAuth App

1. Go to GitHub Settings: https://github.com/settings/developers
2. Click **"OAuth Apps"** in the left sidebar
3. Click **"New OAuth App"** button
4. Fill in the application details:

```
Application name: YourSiteName WordPress Plugin
Homepage URL: https://yoursite.com
Authorization callback URL: https://yoursite.com/wp-admin/admin.php?page=praisonpress-settings&oauth=callback
```

**Important**: Replace `yoursite.com` with your actual domain!

5. Click **"Register application"**

### 2. Get Your Credentials

After creating the app, you'll see:
- **Client ID**: A string like `Ov23liXXXXXXXXXXXXXX`
- **Client Secret**: Click "Generate a new client secret" to create one

**⚠️ IMPORTANT**: Save the Client Secret immediately! You won't be able to see it again.

### 3. Configure PraisonPress Plugin

#### Option A: Using site-config.ini (Recommended)

1. Navigate to your plugin directory:
   ```bash
   cd wp-content/plugins/praisonpressgit/
   ```

2. Create or edit `site-config.ini`:
   ```ini
   [github]
   client_id = "Ov23liXXXXXXXXXXXXXX"
   client_secret = "your_client_secret_here"
   repository_url = "https://github.com/YourUsername/YourContentRepo"
   main_branch = "main"
   ```

3. **Security**: Make sure `site-config.ini` is in `.gitignore` (it already is by default)

#### Option B: Using WordPress Admin UI

1. Go to **WordPress Admin** → **PraisonPress** → **Settings**
2. Scroll to **GitHub Integration** section
3. Enter your **Client ID** and **Client Secret**
4. Enter your **Repository URL**
5. Click **"Save Settings"**

### 4. Connect to GitHub

1. Go to **PraisonPress** → **Settings** in WordPress admin
2. Click **"Connect to GitHub"** button
3. You'll be redirected to GitHub to authorize the app
4. Click **"Authorize"** on GitHub
5. You'll be redirected back to WordPress
6. You should see **"Connected as: YourGitHubUsername"**

---

## Redirect URL Format

The redirect URL must follow this exact format:

```
https://YOUR-DOMAIN.com/wp-admin/admin.php?page=praisonpress-settings&oauth=callback
```

### Examples:

| Site Type | Redirect URL |
|-----------|-------------|
| Production | `https://example.com/wp-admin/admin.php?page=praisonpress-settings&oauth=callback` |
| Subdomain | `https://blog.example.com/wp-admin/admin.php?page=praisonpress-settings&oauth=callback` |
| Subdirectory | `https://example.com/blog/wp-admin/admin.php?page=praisonpress-settings&oauth=callback` |
| Local Dev | `http://localhost:8000/wp-admin/admin.php?page=praisonpress-settings&oauth=callback` |

**⚠️ Important**: 
- Use `https://` for production sites
- Use `http://` only for local development
- The URL must match EXACTLY what's in your GitHub OAuth App settings

---

## Repository Permissions

Your GitHub OAuth App needs access to your content repository. Make sure:

1. **Public Repository**: No special permissions needed
2. **Private Repository**: 
   - The OAuth app must request `repo` scope (already configured in the plugin)
   - The GitHub user authorizing must have access to the repository

---

## Troubleshooting

### "OAuth callback mismatch" Error

**Problem**: The redirect URL doesn't match what's configured in GitHub.

**Solution**: 
1. Check your GitHub OAuth App settings
2. Make sure the redirect URL matches EXACTLY (including http/https, trailing slashes, etc.)
3. Update the URL in GitHub if needed

### "Repository not found" Error

**Problem**: The plugin can't access your repository.

**Solution**:
1. Make sure the repository URL is correct in `site-config.ini`
2. For private repos, make sure you authorized with the correct GitHub account
3. Check that the GitHub user has access to the repository

### "Invalid client_id" Error

**Problem**: The Client ID is incorrect or not configured.

**Solution**:
1. Double-check your Client ID in GitHub OAuth App settings
2. Make sure it's correctly entered in `site-config.ini` or WordPress settings
3. No spaces or quotes around the ID

---

## Security Best Practices

1. **Never commit credentials**: `site-config.ini` is in `.gitignore` by default
2. **Use environment variables**: For production, consider using environment variables
3. **Regenerate secrets**: If your Client Secret is exposed, regenerate it immediately in GitHub
4. **Limit access**: Only give repository access to users who need it

---

## Multiple Environments

If you have multiple environments (dev, staging, production), you need:

### Option 1: Separate OAuth Apps (Recommended)

Create a separate GitHub OAuth App for each environment:
- **Development**: `http://localhost:8000/wp-admin/...`
- **Staging**: `https://staging.example.com/wp-admin/...`
- **Production**: `https://example.com/wp-admin/...`

### Option 2: Multiple Redirect URLs

GitHub allows multiple callback URLs in a single OAuth App:
1. Edit your OAuth App in GitHub
2. Add all your redirect URLs (one per line)
3. Each environment will use the same Client ID/Secret

---

## Example: Complete Setup

Here's a complete example for a production site:

### 1. GitHub OAuth App Settings
```
Application name: MyBlog WordPress Plugin
Homepage URL: https://myblog.com
Authorization callback URL: https://myblog.com/wp-admin/admin.php?page=praisonpress-settings&oauth=callback
```

### 2. site-config.ini
```ini
[github]
client_id = "Ov23liYzAbCdEfGhIjKl"
client_secret = "abc123def456ghi789jkl012mno345pqr678stu"
repository_url = "https://github.com/myusername/myblog-content"
main_branch = "main"
```

### 3. Test the Connection
1. Go to WordPress Admin → PraisonPress → Settings
2. Click "Connect to GitHub"
3. Authorize on GitHub
4. Verify you see "Connected as: myusername"
5. Test by clicking "Sync Now" to pull content

---

## Need Help?

If you encounter issues:
1. Check the WordPress debug log: `wp-content/debug.log`
2. Enable WP_DEBUG in `wp-config.php` for detailed errors
3. Review the GitHub OAuth App settings
4. Make sure your repository URL is correct

---

## Summary Checklist

- [ ] Created GitHub OAuth App with correct redirect URL
- [ ] Saved Client ID and Client Secret
- [ ] Configured `site-config.ini` or WordPress settings
- [ ] Connected to GitHub successfully
- [ ] Tested repository access (Sync Now)
- [ ] Verified content is syncing correctly

Once all steps are complete, your PraisonPress plugin is ready to use!
