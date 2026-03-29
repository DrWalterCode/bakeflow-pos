# GitHub Push Guide

This file explains how an LLM should connect this project to GitHub and push changes safely.

## Repository

- Local path: `C:\Users\Dr Walter\source\repos\BakeFlow POS`
- GitHub owner: `DrWalterCode`
- GitHub repo: `bakeflow-pos`
- Default branch: `main`
- Remote URL: `git@github.com:DrWalterCode/bakeflow-pos.git`

## Important Context

- This project already has a git repository initialised locally.
- SSH auth for `DrWalterCode` was set up on this machine and is the correct way to push.
- Do not rely on any cached HTTPS credential that authenticates as a different GitHub user.
- If GitHub API or connector tools cannot create a repository, use the authenticated browser session to create it on `https://github.com/new`.

## Current SSH Setup

Private key:

```text
%USERPROFILE%\.ssh\id_ed25519_drwaltercode
```

Public key:

```text
%USERPROFILE%\.ssh\id_ed25519_drwaltercode.pub
```

SSH config:

```sshconfig
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_drwaltercode
    IdentitiesOnly yes
```

## Verify Connection

Run this first:

```powershell
ssh -T git@github.com
```

Expected result:

```text
Hi DrWalterCode! You've successfully authenticated, but GitHub does not provide shell access.
```

If this fails, do not push until SSH is fixed.

## Standard Push Workflow

From the project root:

```powershell
git status --short --branch
git remote -v
git branch --show-current
```

If `origin` is missing, add it:

```powershell
git remote add origin git@github.com:DrWalterCode/bakeflow-pos.git
```

If `origin` exists but is wrong, update it:

```powershell
git remote set-url origin git@github.com:DrWalterCode/bakeflow-pos.git
```

Commit changes:

```powershell
git add -A
git commit -m "Describe the change"
```

Push:

```powershell
git push -u origin main
```

After pushing, verify:

```powershell
git status --short --branch
git log --oneline --decorate -3
git remote -v
```

## If the GitHub Repository Does Not Exist Yet

1. Open `https://github.com/new` in a browser session authenticated as `DrWalterCode`.
2. Create repository name `bakeflow-pos`.
3. Do not initialise it with a README, `.gitignore`, or license if local history already exists.
4. After creation, set local remote to `git@github.com:DrWalterCode/bakeflow-pos.git`.
5. Push `main`.

## If SSH Is Missing on a New Machine

Generate a dedicated key:

```powershell
ssh-keygen -t ed25519 -f $HOME\.ssh\id_ed25519_drwaltercode -C "DrWalterCode BakeFlow POS"
```

Then:

1. Add the public key to the `DrWalterCode` GitHub account.
2. Add the SSH config block shown above to `%USERPROFILE%\.ssh\config`.
3. Re-run `ssh -T git@github.com`.
4. Push again.

## Safety Notes

- Confirm which files are actually served by the app before assuming a change is live. In this project, the browser serves assets from `site/assets/`.
- Do not overwrite unrelated user changes in the working tree.
- Do not use destructive git commands such as `git reset --hard` unless explicitly requested.
- Before pushing, always inspect `git status` so screenshots, local databases, logs, or secrets are not committed accidentally.
