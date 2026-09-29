# Troubleshooting

Everytime we run into a issue (mainly setup), add a entry here on

- What the `issue` was
- Steps to taken to `fix it`

Currently Identified Issues

- [Dev container fails to start on Windows](#dev-container-fails-to-start-on-windows)
- [Can't save files into folders](#cant-save-files-into-folders)

---

## Dev container fails to start on Windows

**Symptom:** `Reopen in Container` fails before the container starts, often with a Docker connection or mount error in the Dev Containers log.

**Cause:** either Docker Desktop isn't running, or the Dev Containers extension tries to mount the host's Wayland socket into the container and the mount fails.

**Fix:**

1. Start Docker Desktop and wait until it reports that the engine is running. The dev container can't start without it.
2. Disable the Wayland socket mount in your **user** settings (Ctrl+Shift+P → *Preferences: Open User Settings (JSON)*), not in the workspace settings:

```json
   "dev.containers.mountWaylandSocket": false
```

Then run *Dev Containers: Rebuild and Reopen in Container*.

---

## Can't save files into folders

**Symptom:** Writing into some folders inside `vwork/` fails with *Permission denied* or *Access denied*.

For example

- A browser download
- Saving from an editor on the host
- `git checkout` touching those paths

The same folders work fine from inside the dev container.

**Cause:** the containers run as `root`. Files and folders they create in the
project (through the bind mount) are owned by `root` on your machine, so your own
user can't write to them.

**Who is affected:**

| Host | Affected? |
| --- | --- |
| Linux | Yes |
| Windows, project inside WSL (e.g. `\\wsl$\Ubuntu\home\...` or `~/` in a WSL terminal) | Yes: it's a Linux filesystem, same as above |
| Windows, project on a Windows drive (e.g. `C:\Users\...`) | No: Docker Desktop maps ownership to your Windows user |
| macOS | No: Docker Desktop maps ownership to your macOS user |

**Fix (Linux, or WSL):** from a terminal **outside** the containers, in the
project's root folder, give the files back to your user:

```bash
sudo chown -R "$(id -u):$(id -g)" .
```

Run it again whenever new root-owned folders appear.
