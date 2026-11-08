# GitHub Publishing & Release Guide

This document explains how to publish the repository to GitHub, configure remotes, tag version releases, and package production zip archives.

---

## 1. Publishing to GitHub

### Option A: Via GitHub CLI (`gh`)
If you have the GitHub CLI installed and authenticated:
```bash
# Create a public or private repository directly on GitHub
gh repo create appointment-booking-directory --public --source=. --remote=origin --push
```

### Option B: Via Standard Git Remote
1. Create a new repository on [GitHub](https://github.com/new) named `appointment-booking-directory`.
2. Link your local repository to the GitHub remote:
   ```bash
   git remote add origin https://github.com/YOUR-USERNAME/appointment-booking-directory.git
   ```
3. Push the main branch:
   ```bash
   git branch -M main
   git push -u origin main
   ```

---

## 2. Creating a Production Release & Tag

To cut a formal release on GitHub:

```bash
# 1. Create a signed or annotated Git tag
git tag -a v1.4.0 -m "Release v1.4.0: Appointment Booking & Service Directory Pro"

# 2. Push tags to GitHub
git push origin --tags

# 3. Create a GitHub Release with the bundled plugin zip
gh release create v1.4.0 \
  --title "v1.4.0 - Production Release" \
  --notes "Initial professional release of Appointment Booking & Service Directory Pro." \
  appointment-booking-directory.zip
```

---

## 3. Creating the Distribution ZIP

To generate a clean zip archive ready for uploading to WordPress:

```bash
# Exclude git files, tests, and documentation artifacts if desired:
zip -r appointment-booking-directory.zip . \
  -x "*.git*" \
  -x "*tests*" \
  -x "*php_server.log*" \
  -x "*self_created_tools*"
```

The resulting `appointment-booking-directory.zip` file can be directly uploaded through **Plugins -> Add New -> Upload Plugin** on any live WordPress site.
