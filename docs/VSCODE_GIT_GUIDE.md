# Git with Visual Studio Code - Quick Start Guide

This guide will help you push the VitalPBX Wallboard project to GitHub using Visual Studio Code.

---

## Prerequisites

1. **Visual Studio Code** - Download from https://code.visualstudio.com
2. **Git** - Download from https://git-scm.com/downloads
3. **GitHub Account** - Create at https://github.com

---

## Step 1: Extract the Project

1. Download `VitalPBX-Asterisk-Wallboard.zip`
2. Extract it to a folder (e.g., `C:\Projects\VitalPBX-Asterisk-Wallboard` or `~/Projects/VitalPBX-Asterisk-Wallboard`)

---

## Step 2: Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `VitalPBX-Asterisk-Wallboard`
3. Description: `Real-time call center wallboard for VitalPBX and Asterisk`
4. Select **Public** (or Private if you prefer)
5. **DO NOT** check "Add a README file" (we have one)
6. Click **Create repository**
7. Keep the page open - you'll need the URL

---

## Step 3: Open in VS Code

1. Open Visual Studio Code
2. **File > Open Folder** (or Ctrl+K Ctrl+O)
3. Navigate to the extracted `VitalPBX-Asterisk-Wallboard` folder
4. Click **Select Folder**

---

## Step 4: Initialize Git Repository

### Option A: Using VS Code GUI

1. Click the **Source Control** icon in the left sidebar (or Ctrl+Shift+G)
2. Click **Initialize Repository** button
3. VS Code will initialize Git in the folder

### Option B: Using Terminal in VS Code

1. Open Terminal: **View > Terminal** (or Ctrl+`)
2. Run:
   ```bash
   git init
   ```

---

## Step 5: Stage All Files

### Option A: Using VS Code GUI

1. In Source Control panel, you'll see all files listed under "Changes"
2. Click the **+** (plus) icon next to "Changes" to stage all files
   - Or click the **+** next to each file to stage individually

### Option B: Using Terminal

```bash
git add .
```

---

## Step 6: First Commit

### Option A: Using VS Code GUI

1. In the Source Control panel, type a commit message in the text box:
   ```
   Initial commit - VitalPBX Asterisk Wallboard v1.0
   ```
2. Click the **✓** (checkmark) button to commit
   - Or press Ctrl+Enter

### Option B: Using Terminal

```bash
git commit -m "Initial commit - VitalPBX Asterisk Wallboard v1.0"
```

---

## Step 7: Connect to GitHub

### Option A: Using VS Code GUI

1. Click **"Publish Branch"** button in Source Control panel
2. VS Code will prompt to sign in to GitHub
3. Follow the authentication flow
4. Select your repository from the list
5. Choose **public** or **private**

### Option B: Using Terminal

1. Copy the URL from your GitHub repo page (looks like: `https://github.com/USERNAME/VitalPBX-Asterisk-Wallboard.git`)
2. Run these commands:
   ```bash
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/VitalPBX-Asterisk-Wallboard.git
   git push -u origin main
   ```
3. If prompted, enter your GitHub credentials

---

## Step 8: Verify on GitHub

1. Refresh your GitHub repository page
2. You should see all your files!

---

## Making Future Changes

### After editing files:

1. Open Source Control panel (Ctrl+Shift+G)
2. Changed files appear under "Changes"
3. Click **+** to stage the files you want to commit
4. Enter a commit message
5. Click ✓ to commit
6. Click **Sync** or **Push** to upload to GitHub

### Quick keyboard shortcuts:
- **Ctrl+Shift+G** - Open Source Control
- **Ctrl+Enter** - Commit staged changes
- **Ctrl+Shift+P** then "Git: Push" - Push to remote

---

## Common Git Tasks in VS Code

### Pull latest changes from GitHub
1. Source Control panel > Click **...** (three dots menu)
2. Select **Pull**
   
Or use Terminal:
```bash
git pull
```

### Create a new branch
1. Click branch name in bottom-left corner of VS Code
2. Select "Create new branch"
3. Enter branch name
4. Work on your changes
5. Commit and push as normal

### View file history
1. Right-click a file
2. Select **Open Timeline** or **Git: View File History**

---

## Troubleshooting

### "Please tell me who you are" error
Run in Terminal:
```bash
git config --global user.email "your-email@example.com"
git config --global user.name "Your Name"
```

### Authentication failed
1. Go to GitHub Settings > Developer Settings > Personal Access Tokens
2. Generate new token (classic)
3. Select "repo" scope
4. Use the token as your password when pushing

### VS Code can't find Git
1. Ensure Git is installed: https://git-scm.com/downloads
2. Restart VS Code after installing Git
3. If still not working:
   - Open VS Code Settings (Ctrl+,)
   - Search for "git.path"
   - Set the path to your git executable

---

## Recommended VS Code Extensions

Install these for better Git experience:

1. **GitLens** - Enhanced Git capabilities
   - Click Extensions icon (Ctrl+Shift+X)
   - Search "GitLens"
   - Click Install

2. **Git Graph** - Visual branch history
   - Search "Git Graph"
   - Click Install

---

## Quick Reference Card

| Action | Keyboard | GUI |
|--------|----------|-----|
| Open Source Control | Ctrl+Shift+G | Click Source Control icon |
| Stage file | - | Click + next to file |
| Stage all | - | Click + next to "Changes" |
| Commit | Ctrl+Enter | Click ✓ |
| Push | - | Click Sync/Push |
| Pull | - | Click ... > Pull |

---

## Your Repository URL

After pushing, your repo will be at:
```
https://github.com/YOUR_USERNAME/VitalPBX-Asterisk-Wallboard
```

Share this URL to let others clone and use your wallboard!

---

Good luck! 🚀
