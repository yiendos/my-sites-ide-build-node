# Node

[Node](https://nodejs.org) and npm in a container for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
installs your sites' JavaScript dependencies and builds their assets without Node on the host, and
does both for you when `ide:repo-clone --laravel` clones a site.

Written for: developers running sites in my-sites-ide, including ones moving over from the node
container that used to ship inside the IDE.

## Contents

- [Installation](#installation)
- [Upgrading from the built-in node container](#upgrading-from-the-built-in-node-container)
- [Architecture](#architecture)
- [Command reference](#command-reference)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section of the IDE's `composer.local.json`:

```json
"yiendos/my-sites-ide-build-node": "@dev"
```

Then, from the IDE root:

```
composer update
docker compose build node    # builds the ${NAMESPACE}_frontend image
```

Composer's `post-autoload-dump` hook registers the `build:node-*` commands and the `node` compose
service. The service has `autostart: false` - nothing runs until you call a command, which starts a
throwaway container (`docker compose run --rm node`) and removes it when it's done.

## Upgrading from the built-in node container

| Before (in the IDE) | Now (this plugin) |
|---|---|
| `php my-sites-ide ide:build-assets <site>` | `php my-sites-ide build:node-assets <site>` |
| `ide:repo-clone --laravel` called `ide:build-assets` | the IDE runs the `site-assets` hook, which this plugin hooks `build:node-assets` to. Without the plugin, `--laravel` skips the build and says so |
| `docker compose run --rm node /usr/local/bin/npm --prefix <site>/Sites ...` | `php my-sites-ide build:node-run <site> -- ...` (the raw compose line still works) |
| the build script chosen by file: `webpack.mix.cjs` → `prod`, `vite.config.js` → `build`, anything else skipped | chosen from `package.json`: `prod` for a Laravel Mix site (`webpack.mix.js` or `.cjs`), otherwise `build`, so `vite.config.ts` / `.mjs` sites build too |
| reported success when a step failed | stops at the first failing step and exits non-zero |
| the whole root `.env` passed into the container (`env_file`) | nothing passed in - npm read none of it, and it put secrets such as API keys and passwords in the container's environment |
| no cache - every install downloaded every package again | npm's cache kept in `storage/plugins/node/npm-cache` |

The image keeps its old name, `${NAMESPACE}_frontend`, and is built from the same `Dockerfile`, so an
existing image keeps working.

## Architecture

```
host (my-sites-ide CLI)
  |- build:node-assets <site>          --> docker compose run --rm node npm --prefix <site>/Sites install, then run build|prod
  |- build:node-run <site> -- ...      --> docker compose run --rm node npm --prefix <site>/Sites ...
  |- ide:repo-clone --laravel          --> site-dependencies hook (composer plugin), then
                                           site-assets hook --> build:node-assets <site>

node container (removed after each run)
  /opt/repos         <-- Repos/        (your sites)
  /opt/Packages      <-- Packages/     (for local packages in Packages/)
  /storage/npm-cache <-- storage/plugins/node/npm-cache
```

The image is the official `node:lts-alpine` image, unchanged. It runs as root, as it did in the IDE.

## Command reference

| Command | What it does |
|---|---|
| `build:node-assets <site>` | `npm install` in `Repos/<site>/Sites`, then the build: `npm run prod` for a Laravel Mix site with a `prod` script, otherwise `npm run build` (or `prod` if that's the only one). Skips a site with no `package.json`, and warns when there's no build script |
| `build:node-run <site> -- <arguments>` | Any npm command in `Repos/<site>/Sites`, e.g. `build:node-run example -- run build` or `build:node-run example -- install -D tailwindcss`. Put npm's arguments after `--`, or the CLI takes their options as its own |

There's nothing to configure - the plugin has no `.env` settings.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_frontend` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | the `Repos/` and `Packages/` mounts, finding `Repos/<site>` |
| `storage/plugins/node/` (`"storage": true`) | npm's cache, mounted at `/storage` |
| the `site-assets` hook | building on `ide:repo-clone --laravel`, after the `site-dependencies` hook |

## Troubleshooting

**The build can't find something in `vendor/`** (e.g. a Laravel package's CSS or Ziggy routes). The
site's composer dependencies aren't installed. Install them first:
`php my-sites-ide build:composer-install <site>` with
[yiendos/my-sites-ide-build-composer](https://github.com/yiendos/my-sites-ide-build-composer).

**`no such service: node`.** The plugin isn't installed, or discovery hasn't run since it was:
`composer update`, or `php my-sites-ide ide:plugin-discover`.

**Files in `node_modules/` or `public/build/` are owned by root** (Linux hosts). The container runs
as root (see Known gaps). `sudo chown -R $USER Repos/<site>/Sites` fixes them.

## Known gaps

- No dev server: `npm run dev` (Vite's hot reload) runs, but no port is published, so the browser
  can't reach it. Build with `build:node-assets` instead.
- The container runs as root, so on Linux hosts the files it writes are owned by root.
- No credentials for private npm registries: no `.npmrc` or token reaches the container.
- The IDE's `_dev/Makefile` still has its own `npm` targets calling the compose service directly.
