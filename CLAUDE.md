# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Single-file PHP + React app that renders a classic black-and-white Macintosh desktop in the browser. `index.php` is both the backend (directory-listing JSON API) and the frontend (a React 18 app transpiled in-browser by Babel Standalone via CDN). There is no build step, no package manager, no test suite.

`index-0.0X.php` files are frozen snapshots of earlier versions kept as history — do not edit them when working on the current app. Always edit `index.php`.

## Running locally

The file must be served by PHP for the directory API to work:

```bash
php -S localhost:8000
```

Then open http://localhost:8000/. Opening `index.php` directly via `file://` will break the `?api=1` and `?read=...` endpoints.

## Architecture

**Backend (top of `index.php`, lines 1–86):** Two GET endpoints on the same script:
- `?api=1` — recursively walks the directory the script lives in via `scandir`, returns a JSON tree of `{id, name, type, x, y, children?, url?}` nodes used to populate the desktop. The script hides itself from the listing.
- `?read=<relpath>` — reads a file's text contents, with a `realpath` containment check against `__DIR__` to prevent traversal outside the base directory. Currently unused by the frontend (files open in a new tab via their `url`).

The PHP block exits before the HTML, so the HTML/React is only served when neither query param is set.

**Frontend (the `<script type="text/babel">` block):** One React component tree, all defined in-file:
- `App` — top-level state: `windows[]`, `desktopItems[]`, `selectedIcon`, `zIndexCounter`, plus four menu-open booleans and a `clockTime` ticker. On mount, fetches `?api=1`, renames the root folder to "Projects", positions the Projects/TrailKit/PlanFit/Trash icons along the right edge, and opens two default windows (`About Tyler Geddes` and the `TrailKit Story` iframe).
- `DesktopIcon` — defined **outside** `App` on purpose, so its local `useState` (position, `tipped`) is preserved across `App` re-renders. Handles pointer-capture drag on the desktop, and double-click to either open a window (folder) or trigger the trash-tipping CSS animation (trash icon).
- `DraggableWindow` — also **outside** `App` for the same reason. Owns its own `pos` and `size` state and uses pointer capture for both title-bar drag and bottom-right resize. Renders one of four content modes based on `win.type`: `text`, `iframe`, `splash` (one of the two big SVG splash screens defined inline as `TrailKitSplashSVG` / `PlanFitSplashSVG`), or a folder grid of child `DesktopIcon`s.

**Window/z-index model:** `zIndexCounter` increments on every focus; `bringToFront(id)` rewrites just that window's `zIndex`. Windows are keyed by `id`; opening the same `id` twice focuses the existing window instead of duplicating it. `app`/`file` types short-circuit `openWindow` and call `window.open(url, '_blank')` instead of opening a Mac window.

**Menu bar:** Apple / Apps / Tools / Thoughts dropdowns. The Tools and Thoughts menus open iframes pointing at paths (`trailkit/trailkitstory/index.html`, `JD-Extractor/JD-Extractor.html`, `Resume-Catalogue/Resume-Catalogue.html`) that don't exist in this repo — they live alongside `index.php` on the deployed server. Locally those iframes will 404, which is expected.

## Things to watch for when editing

- Components that hold per-instance state (`DesktopIcon`, `DraggableWindow`) must stay defined outside `App`. Moving them inside `App` will remount them on every parent render and lose drag positions, window sizes, and the trash tip state.
- Babel Standalone transpiles in the browser on every load — there is no cache. Keep an eye on the script size if you add a lot of code.
- Icon positions for the right-edge column (Projects/TrailKit/PlanFit/Trash) are recomputed in the `resize` handler in `App`'s effect; if you add another permanent desktop icon, add it to both the initial `setDesktopItems` call and the resize handler.
- The `?api=1` listing exposes every file in the script's directory to the public. Don't drop secrets, `.env` files, or unrelated content into this directory on a live deploy.
