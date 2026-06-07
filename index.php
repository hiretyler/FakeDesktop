<?php
// ============================================================================
// PHP BACKEND: Securely reads the directory and files
// ============================================================================

// 1. API Route: Return the directory structure as JSON
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    $baseDir = realpath(__DIR__);
    
    function getDirectoryTree($dir, $baseDir) {
        $result = [];
        $items = @scandir($dir);
        if (!$items) return [];
        
        $x = 20; $y = 20; // Starting coordinates for icons in folders
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            // Hide this script itself from the desktop view
            if ($dir === $baseDir && $item === basename(__FILE__)) continue; 
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($path);
            
            // Get path relative to the base directory for secure fetching later
            $relPath = substr($path, strlen($baseDir));
            $relPath = ltrim($relPath, DIRECTORY_SEPARATOR);
            
            $node = [
                'id' => md5($path),
                'name' => $item,
                'type' => $isDir ? 'folder' : 'file',
                'x' => $x,
                'y' => $y
            ];
            
            if ($isDir) {
                $node['children'] = getDirectoryTree($path, $baseDir);
            } else {
                // Point directly to the file relative to the current script
                $node['url'] = $relPath;
            }
            
            $result[] = $node;
            
            // Arrange icons in a simple grid
            $x += 80;
            if ($x > 300) {
                $x = 20;
                $y += 80;
            }
        }
        return $result;
    }
    
    $tree = [
        'id' => 'root',
        'name' => basename($baseDir) ?: 'Server Root',
        'type' => 'folder',
        'x' => 20,
        'y' => 60,
        'children' => getDirectoryTree($baseDir, $baseDir)
    ];
    
    echo json_encode($tree);
    exit;
}

// 2. API Route: Securely read a file's content (Optional fallback)
if (isset($_GET['read'])) {
    $baseDir = realpath(__DIR__);
    $requestedPath = $_GET['read'];
    $file = realpath($baseDir . DIRECTORY_SEPARATOR . $requestedPath);
    
    if ($file && strpos($file, $baseDir) === 0 && is_file($file)) {
        header('Content-Type: text/plain');
        readfile($file);
    } else {
        http_response_code(404);
        echo "File not found or access denied.";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tyler Geddes: PROJECTS</title>
    
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chicago&display=swap');
        
        :root {
          --mac-black: #000000;
          --mac-white: #ffffff;
        }

        body {
          margin: 0;
          overflow: hidden;
          user-select: none;
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          -webkit-font-smoothing: none;
        }

        .mac-desktop {
          background-color: #AAAAAA;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='2' height='2'%3E%3Crect width='2' height='2' fill='%23ffffff'/%3E%3Crect width='1' height='1' fill='%23000000'/%3E%3Crect x='1' y='1' width='1' height='1' fill='%23000000'/%3E%3C/svg%3E");
          background-repeat: repeat;
          height: 100vh;
          width: 100vw;
          position: relative;
        }

        .mac-menubar {
          background: var(--mac-white);
          border-bottom: 2px solid var(--mac-black);
          height: 24px;
          display: flex;
          align-items: center;
          padding: 0 10px;
          font-size: 14px;
          font-weight: bold;
          z-index: 9999;
          position: relative;
        }

        .mac-menu-item {
          padding: 0 8px;
          cursor: pointer;
          height: 100%;
          display: flex;
          align-items: center;
        }

        .mac-menu-item:hover {
          background: var(--mac-black);
          color: var(--mac-white);
        }

        .mac-menu-item:hover svg {
          fill: var(--mac-white);
        }

        .mac-window {
          position: absolute;
          background: var(--mac-white);
          border: 2px solid var(--mac-black);
          box-shadow: 2px 2px 0px rgba(0,0,0,1);
          display: flex;
          flex-direction: column;
          min-width: 200px;
          min-height: 150px;
        }

        .mac-titlebar {
          height: 20px;
          border-bottom: 2px solid var(--mac-black);
          display: flex;
          align-items: center;
          justify-content: center;
          position: relative;
          cursor: grab;
          background: repeating-linear-gradient(
            to bottom,
            var(--mac-white),
            var(--mac-white) 1px,
            var(--mac-black) 1px,
            var(--mac-black) 2px
          );
        }

        .mac-titlebar:active {
          cursor: grabbing;
        }

        .mac-titlebar-text {
          background: var(--mac-white);
          padding: 0 10px;
          font-size: 14px;
          font-weight: bold;
          border: 2px solid var(--mac-black);
          z-index: 1;
          margin-top: -2px;
        }

        .mac-close-box {
          position: absolute;
          left: 4px;
          top: 2px;
          width: 12px;
          height: 12px;
          background: var(--mac-white);
          border: 2px solid var(--mac-black);
          cursor: pointer;
          z-index: 2;
        }

        .mac-close-box::after {
          content: '';
          position: absolute;
          top: 2px;
          left: 2px;
          width: 4px;
          height: 4px;
          border: 2px solid var(--mac-black);
        }
          
        .mac-close-box:active {
          background: var(--mac-black);
        }

        .mac-window-content {
          flex: 1;
          padding: 10px;
          overflow: auto;
          position: relative;
          background: var(--mac-white);
        }

        .mac-icon-container {
          position: absolute;
          display: flex;
          flex-direction: column;
          align-items: center;
          width: 64px;
          cursor: pointer;
        }

        .mac-icon-label {
          margin-top: 4px;
          font-size: 12px;
          text-align: center;
          word-wrap: break-word;
          background: var(--mac-white);
          padding: 0 2px;
        }

        .mac-icon-container.selected .mac-icon-label {
          background: var(--mac-black);
          color: var(--mac-white);
        }

        .mac-text-content {
          font-family: monospace;
          font-size: 14px;
          white-space: pre-wrap;
        }
        
        .mac-loading {
          display: flex;
          justify-content: center;
          align-items: center;
          height: 100%;
          font-style: italic;
          color: #555;
        }

        .mac-resize-handle {
          position: absolute;
          right: 1px;
          bottom: 1px;
          width: 14px;
          height: 14px;
          cursor: nwse-resize;
          display: flex;
          align-items: flex-end;
          justify-content: flex-end;
          padding: 2px;
          box-sizing: border-box;
          background: var(--mac-white);
          z-index: 10;
        }

        .mac-dropdown {
          position: absolute;
          top: 24px;
          left: -2px;
          background: var(--mac-white);
          border: 2px solid var(--mac-black);
          box-shadow: 2px 2px 0px rgba(0,0,0,1);
          display: flex;
          flex-direction: column;
          z-index: 10000;
          min-width: 180px;
        }

        .mac-dropdown-item {
          padding: 4px 10px;
          cursor: pointer;
          font-weight: normal;
          font-size: 14px;
          color: var(--mac-black);
        }

        .mac-dropdown-item:hover {
          background: var(--mac-black);
          color: var(--mac-white);
        }

        .mac-dropdown-divider {
          height: 2px;
          background: var(--mac-black);
          margin: 2px 0;
        }

        .shutdown-overlay {
          position: fixed;
          top: 0; left: 0; width: 100vw; height: 100vh;
          background: black;
          z-index: 99999;
          opacity: 0;
          transition: opacity 1.5s ease-in-out;
          pointer-events: none;
        }

        .shutdown-overlay.active {
          opacity: 1;
          pointer-events: all;
        }

        /* Trash Animation Styles */
        .trash-icon {
          overflow: visible !important;
        }
        @keyframes tipCan {
          0% { transform: rotate(0deg); }
          70% { transform: rotate(-95deg); }
          85% { transform: rotate(-88deg); }
          100% { transform: rotate(-90deg); }
        }
        @keyframes flyLid {
          0% { transform: translate(0, 0) rotate(0deg); }
          100% { transform: translate(-35px, 25px) rotate(-160deg); }
        }
        @keyframes spillRubbish {
          0% { transform: translate(0, 0); opacity: 0; }
          20% { transform: translate(-5px, -10px); opacity: 1; }
          100% { transform: translate(-35px, 15px); opacity: 1; }
        }
        .trash-icon.tipped .trash-can {
          animation: tipCan 0.6s forwards ease-in-out;
        }
        .trash-icon.tipped .trash-lid {
          animation: flyLid 0.7s forwards cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .trash-icon.tipped .trash-rubbish {
          animation: spillRubbish 0.8s forwards ease-out 0.1s;
        }
        .trash-icon:not(.tipped) .trash-rubbish {
          opacity: 0;
        }

        /* ============================================================ */
        /* EASTER EGG: Menu Bar Mischief                                */
        /* ============================================================ */
        .ee-bomb-overlay {
          position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
          z-index: 100000;
          display: flex; align-items: center; justify-content: center;
        }
        .ee-bomb-dialog {
          background: var(--mac-white);
          border: 2px solid var(--mac-black);
          box-shadow: 2px 2px 0px rgba(0,0,0,1);
          width: 340px;
          display: flex; flex-direction: column;
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          -webkit-font-smoothing: none;
          position: relative;
        }
        .ee-bomb-body {
          display: flex; flex-direction: row; align-items: center;
          padding: 18px 16px 12px 16px; gap: 16px;
        }
        .ee-bomb-svg-wrap { flex-shrink: 0; }
        .ee-bomb-text { flex: 1; }
        .ee-bomb-text p {
          margin: 0 0 4px 0; font-size: 13px;
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          -webkit-font-smoothing: none; line-height: 1.4;
        }
        .ee-bomb-text .ee-bomb-id { font-size: 11px; margin-top: 6px; }
        .ee-bomb-divider { height: 2px; background: var(--mac-black); margin: 0; }
        .ee-bomb-buttons {
          display: flex; flex-direction: row;
          padding: 10px 16px 12px 16px; justify-content: center; gap: 12px;
        }
        .ee-bomb-btn {
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          font-size: 13px; -webkit-font-smoothing: none;
          background: var(--mac-white); color: var(--mac-black);
          border: 2px solid var(--mac-black);
          box-shadow: 2px 2px 0px rgba(0,0,0,1);
          padding: 4px 18px; cursor: pointer; border-radius: 4px;
          min-width: 80px; text-align: center; line-height: 1.4;
        }
        .ee-bomb-btn:active {
          background: var(--mac-black); color: var(--mac-white);
          box-shadow: none; transform: translate(2px, 2px);
        }
        .ee-bomb-btn-disabled { color: #888; cursor: not-allowed; box-shadow: none; }
        .ee-bomb-btn-disabled:active {
          background: var(--mac-white); color: #888; box-shadow: none; transform: none;
        }
        .ee-bomb-reboot-flash {
          position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
          background: var(--mac-black);
          z-index: 200000; opacity: 0; pointer-events: none;
          transition: opacity 0.15s ease-in;
        }
        .ee-bomb-reboot-flash.active { opacity: 1; pointer-events: all; }

        .ee-aboutmac-content {
          padding: 12px 14px 10px 14px;
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          font-size: 12px; -webkit-font-smoothing: none;
          background: var(--mac-white); height: 100%; box-sizing: border-box;
          overflow: hidden; display: flex; flex-direction: column;
        }
        .ee-aboutmac-header { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
        .ee-aboutmac-header-text { flex: 1; }
        .ee-aboutmac-header-text .ee-aboutmac-sysname { font-size: 14px; font-weight: bold; margin: 0 0 2px 0; }
        .ee-aboutmac-header-text .ee-aboutmac-copyright { font-size: 11px; margin: 0; }
        .ee-aboutmac-divider { height: 2px; background: var(--mac-black); margin: 6px 0; }
        .ee-aboutmac-specs { display: flex; flex-direction: column; gap: 3px; margin-bottom: 8px; }
        .ee-aboutmac-spec-row { display: flex; justify-content: space-between; font-size: 12px; }
        .ee-aboutmac-spec-value { font-weight: bold; }
        .ee-aboutmac-membar-section { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .ee-aboutmac-membar-label { font-size: 12px; font-weight: bold; margin-bottom: 4px; }
        .ee-aboutmac-membar {
          height: 18px; border: 2px solid var(--mac-black);
          display: flex; overflow: hidden; flex-shrink: 0;
        }
        .ee-aboutmac-membar-seg {
          height: 100%; box-sizing: border-box;
          border-right: 2px solid var(--mac-black); position: relative; overflow: hidden;
        }
        .ee-aboutmac-membar-seg:last-child { border-right: none; }
        .ee-aboutmac-seg-system { background-color: var(--mac-black); }
        .ee-aboutmac-seg-win0 {
          background-color: var(--mac-white);
          background-image: repeating-linear-gradient(45deg, var(--mac-black) 0px, var(--mac-black) 2px, transparent 2px, transparent 6px);
        }
        .ee-aboutmac-seg-win1 {
          background-color: var(--mac-white);
          background-image: radial-gradient(circle, var(--mac-black) 1.5px, transparent 1.5px);
          background-size: 5px 5px;
        }
        .ee-aboutmac-seg-win2 {
          background-color: var(--mac-white);
          background-image: repeating-linear-gradient(-45deg, var(--mac-black) 0px, var(--mac-black) 2px, transparent 2px, transparent 6px);
        }
        .ee-aboutmac-seg-win3 {
          background-color: var(--mac-white);
          background-image:
            repeating-linear-gradient(0deg, var(--mac-black) 0px, var(--mac-black) 1px, transparent 1px, transparent 6px),
            repeating-linear-gradient(90deg, var(--mac-black) 0px, var(--mac-black) 1px, transparent 1px, transparent 6px);
        }
        .ee-aboutmac-seg-win4 {
          background-color: var(--mac-white);
          background-image: repeating-linear-gradient(0deg, var(--mac-black) 0px, var(--mac-black) 1px, transparent 1px, transparent 4px);
        }
        .ee-aboutmac-seg-win5 {
          background-color: var(--mac-white);
          background-image: repeating-linear-gradient(90deg, var(--mac-black) 0px, var(--mac-black) 1px, transparent 1px, transparent 4px);
        }
        .ee-aboutmac-seg-unused { background-color: var(--mac-white); }
        .ee-aboutmac-membar-legend { display: flex; flex-wrap: nowrap; margin-top: 4px; overflow: hidden; }
        .ee-aboutmac-legend-item {
          display: flex; flex-direction: column;
          font-size: 9px; line-height: 1.2; overflow: hidden;
          padding-right: 4px; min-width: 0;
        }
        .ee-aboutmac-legend-name {
          white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
        }
        .ee-aboutmac-legend-kb { color: #444; }

        /* ============================================================ */
        /* EASTER EGG: Flying Toasters (After Dark)                    */
        /* ============================================================ */
        .ee-toaster-overlay {
          position: fixed; inset: 0;
          background: #000; z-index: 99998;
          overflow: hidden; cursor: pointer;
        }
        .ee-toaster-entity {
          position: absolute;
          animation-name: ee-toaster-fly;
          animation-timing-function: linear;
          animation-iteration-count: infinite;
          will-change: transform;
        }
        @keyframes ee-toaster-fly {
          0%   { transform: translate(0, 0) scale(var(--ee-scale, 1)); }
          100% { transform: translate(-140vw, 120vh) scale(var(--ee-scale, 1)); }
        }
        @keyframes ee-wing-left-flap {
          0%   { transform: rotate(0deg);   }
          40%  { transform: rotate(-35deg); }
          100% { transform: rotate(0deg);   }
        }
        @keyframes ee-wing-right-flap {
          0%   { transform: rotate(0deg);  }
          40%  { transform: rotate(35deg); }
          100% { transform: rotate(0deg);  }
        }
        .ee-toaster-wing-left {
          transform-origin: 100% 50%;
          animation: ee-wing-left-flap 200ms ease-in-out infinite;
        }
        .ee-toaster-wing-right {
          transform-origin: 0% 50%;
          animation: ee-wing-right-flap 200ms ease-in-out infinite;
        }

        /* ============================================================ */
        /* SCREENSAVER: Starfield warp                                 */
        /* ============================================================ */
        .ee-screensaver-overlay {
          position: fixed; inset: 0;
          background: #000; z-index: 99999;
          overflow: hidden; cursor: none;
        }
        .ee-screensaver-overlay canvas { display: block; }

        /* ============================================================ */
        /* EASTER EGG: Force Quit                                      */
        /* ============================================================ */
        .ee-forcequit-backdrop {
          position: fixed; inset: 0; z-index: 99997;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='2' height='2'%3E%3Crect width='2' height='2' fill='%23000000'/%3E%3Crect x='1' y='0' width='1' height='1' fill='%23ffffff' opacity='0.15'/%3E%3C/svg%3E");
          background-repeat: repeat;
        }
        .ee-forcequit-modal {
          position: fixed; top: 50%; left: 50%;
          transform: translate(-50%, -50%); z-index: 99998;
          background: var(--mac-white);
          border: 2px solid var(--mac-black);
          box-shadow: 2px 2px 0px rgba(0,0,0,1);
          width: 320px; display: flex; flex-direction: column;
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          -webkit-font-smoothing: none;
        }
        .ee-forcequit-titlebar {
          height: 20px; border-bottom: 2px solid var(--mac-black);
          display: flex; align-items: center; justify-content: center;
          background: repeating-linear-gradient(to bottom,
            var(--mac-white), var(--mac-white) 1px,
            var(--mac-black) 1px, var(--mac-black) 2px);
          position: relative; flex-shrink: 0;
        }
        .ee-forcequit-titlebar-text {
          background: var(--mac-white); padding: 0 10px;
          font-size: 13px; font-weight: bold;
          border: 2px solid var(--mac-black);
          z-index: 1; margin-top: -2px; white-space: nowrap;
        }
        .ee-forcequit-list {
          max-height: 220px; overflow-y: auto;
          border-bottom: 2px solid var(--mac-black);
        }
        .ee-forcequit-row {
          display: flex; align-items: center;
          padding: 4px 10px; gap: 8px; cursor: pointer;
          font-size: 13px; color: var(--mac-black);
          background: var(--mac-white);
          border-bottom: 1px solid #ccc; user-select: none;
        }
        .ee-forcequit-row:last-child { border-bottom: none; }
        .ee-forcequit-row.selected { background: var(--mac-black); color: var(--mac-white); }
        .ee-forcequit-row.selected svg rect,
        .ee-forcequit-row.selected svg path,
        .ee-forcequit-row.selected svg polygon { fill: var(--mac-white); stroke: var(--mac-white); }
        .ee-forcequit-system { font-style: italic; color: #444; }
        .ee-forcequit-row.selected.ee-forcequit-system { color: #ccc; }
        .ee-forcequit-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 8px 10px; flex-shrink: 0; }
        .ee-forcequit-btn {
          font-family: 'Chicago', 'Trebuchet MS', sans-serif;
          font-size: 13px; font-weight: bold; -webkit-font-smoothing: none;
          background: var(--mac-white); color: var(--mac-black);
          border: 2px solid var(--mac-black);
          box-shadow: 2px 2px 0px rgba(0,0,0,1);
          padding: 3px 14px; cursor: pointer;
        }
        .ee-forcequit-btn:active {
          background: var(--mac-black); color: var(--mac-white);
          box-shadow: none; transform: translate(2px, 2px);
        }
        .ee-forcequit-btn-primary { outline: 2px solid var(--mac-black); outline-offset: 2px; }
        .ee-forcequit-subalert {
          padding: 6px 10px; font-size: 12px; font-style: italic;
          color: #333; border-top: 1px solid #ccc; min-height: 20px; flex-shrink: 0;
        }

        /* ============================================================ */
        /* EASTER EGG: Icon Physics                                    */
        /* ============================================================ */
        @keyframes ee-poof-expand {
          0%   { transform: scale(0.2); opacity: 1; }
          60%  { transform: scale(1.1); opacity: 0.8; }
          100% { transform: scale(1.4); opacity: 0; }
        }
        .ee-poof-cloud {
          position: absolute; pointer-events: none; z-index: 9000;
          animation: ee-poof-expand 500ms forwards ease-out;
          transform-origin: center center;
        }
        .ee-smug-face {
          position: absolute; top: -2px; right: -4px;
          pointer-events: none; z-index: 5;
          animation: ee-smug-fadein 200ms ease-out forwards;
        }
        @keyframes ee-smug-fadein {
          from { opacity: 0; transform: scale(0.5); }
          to   { opacity: 1; transform: scale(1); }
        }
        .ee-smug-face.ee-smug-fadeout { animation: ee-smug-fadeout 400ms ease-in forwards; }
        @keyframes ee-smug-fadeout {
          from { opacity: 1; transform: scale(1); }
          to   { opacity: 0; transform: scale(0.6); }
        }
        @keyframes ee-dizzy-wobble {
          0%   { transform: rotate(-3deg); }
          50%  { transform: rotate(3deg); }
          100% { transform: rotate(-3deg); }
        }
        .ee-dizzy-icon { animation: ee-dizzy-wobble 150ms linear infinite; }
        .ee-dizzy-stars {
          position: absolute; top: -22px; left: 50%;
          transform: translateX(-50%); width: 48px; height: 20px;
          pointer-events: none; z-index: 5;
        }
        @keyframes ee-dizzy-orbit {
          from { transform: rotate(0deg) translateX(20px) rotate(0deg); }
          to   { transform: rotate(360deg) translateX(20px) rotate(-360deg); }
        }
        .ee-dizzy-star:nth-child(1) { animation: ee-dizzy-orbit 800ms linear infinite; }
        .ee-dizzy-star:nth-child(2) { animation: ee-dizzy-orbit 800ms linear infinite; animation-delay: -267ms; }
        .ee-dizzy-star:nth-child(3) { animation: ee-dizzy-orbit 800ms linear infinite; animation-delay: -533ms; }
        .ee-dizzy-star {
          position: absolute; top: 50%; left: 50%;
          margin-top: -4px; margin-left: -4px;
        }

        /* ============================================================ */
        /* EASTER EGG: Hidden Volumes (Floppies)                       */
        /* ============================================================ */
        @keyframes ee-floppy-appear {
          from { opacity: 0; transform: translateX(12px); }
          to   { opacity: 1; transform: translateX(0); }
        }
        .ee-floppy-icon { animation: ee-floppy-appear 0.35s ease-out; }

        /* ============================================================ */
        /* EASTER EGG: Sound menu item                                 */
        /* ============================================================ */
        .ee-sound-menu-item-check {
          display: inline-block; width: 14px; margin-right: 2px; text-align: center;
        }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
      const { useState, useEffect, useRef } = React;

      // ─── EASTER EGG: System Sounds ─────────────────────────────────────
      const SoundSystem = {
        ctx: null,
        enabled: true,

        ensureCtx() {
          if (!this.ctx) {
            try {
              this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            } catch (_) {
              this.ctx = null;
            }
          }
          if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume().catch(() => {});
          }
          return this.ctx;
        },

        playBoot() {
          if (!this.enabled) return;
          const ctx = this.ensureCtx();
          if (!ctx) return;
          const now = ctx.currentTime;
          const freqs = [261.63, 329.63, 392.00, 523.25];
          const master = ctx.createGain();
          master.gain.setValueAtTime(0.0, now);
          master.gain.linearRampToValueAtTime(0.22, now + 0.04);
          master.gain.setValueAtTime(0.22, now + 0.4);
          master.gain.exponentialRampToValueAtTime(0.001, now + 1.8);
          master.connect(ctx.destination);

          freqs.forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq, now);
            const startOffset = i * 0.015;
            gain.gain.setValueAtTime(0.0, now + startOffset);
            gain.gain.linearRampToValueAtTime(1.0 / freqs.length, now + startOffset + 0.03);
            gain.gain.setValueAtTime(1.0 / freqs.length, now + startOffset + 0.3);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 1.7);
            osc.connect(gain);
            gain.connect(master);
            osc.start(now + startOffset);
            osc.stop(now + 1.9);
          });
        },

        playSosumi() {
          if (!this.enabled) return;
          const ctx = this.ensureCtx();
          if (!ctx) return;
          const now = ctx.currentTime;
          const notes = [
            { freq: 659.25, start: 0.00, dur: 0.18 },
            { freq: 523.25, start: 0.20, dur: 0.20 }
          ];
          notes.forEach(({ freq, start, dur }) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now + start);
            gain.gain.setValueAtTime(0.0, now + start);
            gain.gain.linearRampToValueAtTime(0.25, now + start + 0.015);
            gain.gain.setValueAtTime(0.25, now + start + dur - 0.04);
            gain.gain.linearRampToValueAtTime(0.0, now + start + dur);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now + start);
            osc.stop(now + start + dur + 0.01);
          });
        },

        playQuack() {
          if (!this.enabled) return;
          const ctx = this.ensureCtx();
          if (!ctx) return;
          const now = ctx.currentTime;
          const osc = ctx.createOscillator();
          const filter = ctx.createBiquadFilter();
          const gain = ctx.createGain();
          osc.type = 'sawtooth';
          osc.frequency.setValueAtTime(520, now);
          osc.frequency.exponentialRampToValueAtTime(160, now + 0.25);
          filter.type = 'bandpass';
          filter.frequency.setValueAtTime(800, now);
          filter.frequency.exponentialRampToValueAtTime(300, now + 0.25);
          filter.Q.setValueAtTime(2.5, now);
          gain.gain.setValueAtTime(0.0, now);
          gain.gain.linearRampToValueAtTime(0.28, now + 0.02);
          gain.gain.setValueAtTime(0.28, now + 0.15);
          gain.gain.linearRampToValueAtTime(0.0, now + 0.30);
          osc.connect(filter);
          filter.connect(gain);
          gain.connect(ctx.destination);
          osc.start(now);
          osc.stop(now + 0.32);
        }
      };

      const FolderIcon = ({ selected }) => (
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M2 6H12L14 10H30V26H2V6Z" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2" strokeLinejoin="miter"/>
          <path d="M2 10H30" stroke={selected ? "#FFF" : "#000"} strokeWidth="2"/>
        </svg>
      );

      const FileIcon = ({ selected }) => (
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 2H18L26 10V30H6V2Z" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2" strokeLinejoin="miter"/>
          <path d="M18 2V10H26" stroke="#000" strokeWidth="2" fill={selected ? "#000" : "#FFF"} strokeLinejoin="miter"/>
        </svg>
      );

      const TrashIcon = ({ selected, tipped }) => (
        <svg className={`trash-icon ${tipped ? 'tipped' : ''}`} width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ overflow: 'visible' }}>
          <g className="trash-rubbish">
            <path d="M12 12 L18 10 L20 15 L15 18 Z" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="1"/>
            <circle cx="15" cy="14" r="3" fill="#000" />
            <rect x="16" y="16" width="5" height="4" fill="#FFF" stroke="#000" strokeWidth="1" />
          </g>
          <g className="trash-can" style={{ transformOrigin: '6px 30px' }}>
            <rect x="6" y="8" width="20" height="22" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2"/>
          </g>
          <g className="trash-lid" style={{ transformOrigin: '16px 8px' }}>
            <path d="M4 8H28" stroke="#000" strokeWidth="2"/>
            <path d="M12 8V4H20V8" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2"/>
          </g>
        </svg>
      );

      const TKIcon = ({ selected }) => (
        <svg width="56" height="44" viewBox="0 0 56 44" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ overflow: 'visible' }}>
          <g transform="translate(2,2)">
            {/* Card Background & Border */}
            <rect x="0" y="0" width="50" height="38" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2" />
            <rect x="2" y="2" width="46" height="24" fill={selected ? "#000" : "#FFF"} stroke={selected ? "#FFF" : "#000"} strokeWidth="1" />
            
            {/* Sky Dithering */}
            <line x1="2" y1="4" x2="48" y2="4" stroke={selected ? "#FFF" : "#000"} strokeWidth="1" strokeDasharray="1 3" />
            <line x1="4" y1="6" x2="48" y2="6" stroke={selected ? "#FFF" : "#000"} strokeWidth="1" strokeDasharray="1 3" />
            <line x1="2" y1="8" x2="48" y2="8" stroke={selected ? "#FFF" : "#000"} strokeWidth="1" strokeDasharray="1 3" />
            
            {/* Sun */}
            <circle cx="38" cy="10" r="4" fill="none" stroke={selected ? "#FFF" : "#000"} strokeWidth="1" />
            
            {/* Mountains */}
            <path d="M2 26 L14 12 L22 22 L34 6 L48 26 Z" fill={selected ? "#000" : "#FFF"} stroke={selected ? "#FFF" : "#000"} strokeWidth="1.5" strokeLinejoin="round" />
            
            {/* Mountain Snow Caps */}
            <path d="M14 12 L10 16.5 L12 15 L14 17 L16 15 L18 17.5 Z" fill={selected ? "#FFF" : "#000"} />
            <path d="M34 6 L29 12 L31 11 L34 14 L37 11 L39 13 Z" fill={selected ? "#FFF" : "#000"} />
            
            {/* Pine Trees (Foreground) */}
            <path d="M8 26 L8 22 L6 22 L9 16 L7 16 L10 10 L13 16 L11 16 L14 22 L12 22 L12 26 Z" fill={selected ? "#FFF" : "#000"} stroke={selected ? "#000" : "#FFF"} strokeWidth="1"/>
            <path d="M42 26 L42 22 L40 22 L43 16 L41 16 L44 10 L47 16 L45 16 L48 22 L46 22 L46 26 Z" fill={selected ? "#FFF" : "#000"} stroke={selected ? "#000" : "#FFF"} strokeWidth="1"/>

            {/* Title Area */}
            <rect x="0" y="27" width="50" height="11" fill={selected ? "#FFF" : "#000"} />
            <text x="25" y="35" fontFamily="Chicago, 'Trebuchet MS', sans-serif" fontSize="8" fontWeight="bold" textAnchor="middle" fill={selected ? "#000" : "#FFF"} letterSpacing="1">TRAILKIT</text>
          </g>
          {/* Hypercard Stack Shadow Effect */}
          <path d="M52 4 V40 H4" fill="none" stroke="#000" strokeWidth="2" />
          <path d="M54 6 V42 H6" fill="none" stroke="#000" strokeWidth="2" />
        </svg>
      );

      const PFIcon = ({ selected }) => (
        <svg width="56" height="44" viewBox="0 0 56 44" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ overflow: 'visible' }}>
          <g transform="translate(2,2)">
            {/* Card Background & Border */}
            <rect x="0" y="0" width="50" height="38" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2" />
            <rect x="2" y="2" width="46" height="24" fill={selected ? "#000" : "#FFF"} stroke={selected ? "#FFF" : "#000"} strokeWidth="1" />
            
            {/* Grid Background (Calendar/Planner) */}
            <path d="M2 8 H48 M2 14 H48 M2 20 H48" stroke={selected ? "#FFF" : "#000"} strokeWidth="1" strokeDasharray="2 2" />
            <path d="M12 2 V26 M24 2 V26 M36 2 V26" stroke={selected ? "#FFF" : "#000"} strokeWidth="1" strokeDasharray="2 2" />
            
            {/* Stopwatch Icon */}
            <circle cx="16" cy="14" r="6" fill={selected ? "#000" : "#FFF"} stroke={selected ? "#FFF" : "#000"} strokeWidth="1.5" />
            <path d="M16 14 L16 10 M16 14 L18.5 15.5" stroke={selected ? "#FFF" : "#000"} strokeWidth="1.5" />
            <path d="M14 6 H18 M15 5 H17" stroke={selected ? "#FFF" : "#000"} strokeWidth="1.5" />
            
            {/* Dumbbell Icon */}
            <rect x="30" y="10" width="4" height="8" fill={selected ? "#FFF" : "#000"} stroke={selected ? "#000" : "#FFF"} strokeWidth="0.5"/>
            <rect x="42" y="10" width="4" height="8" fill={selected ? "#FFF" : "#000"} stroke={selected ? "#000" : "#FFF"} strokeWidth="0.5"/>
            <rect x="28" y="12" width="2" height="4" fill={selected ? "#FFF" : "#000"} />
            <rect x="46" y="12" width="2" height="4" fill={selected ? "#FFF" : "#000"} />
            <rect x="34" y="13" width="8" height="2" fill={selected ? "#FFF" : "#000"} />
            
            {/* Title Area */}
            <rect x="0" y="27" width="50" height="11" fill={selected ? "#FFF" : "#000"} />
            <text x="25" y="35" fontFamily="Chicago, 'Trebuchet MS', sans-serif" fontSize="8" fontWeight="bold" textAnchor="middle" fill={selected ? "#000" : "#FFF"} letterSpacing="1">PLANFIT</text>
          </g>
          {/* Hypercard Stack Shadow Effect */}
          <path d="M52 4 V40 H4" fill="none" stroke="#000" strokeWidth="2" />
          <path d="M54 6 V42 H6" fill="none" stroke="#000" strokeWidth="2" />
        </svg>
      );

      // EASTER EGG: Floppy disk icon (hidden volumes)
      const FloppyIcon = ({ selected, variant }) => {
        const fg = selected ? '#FFF' : '#000';
        const bg = selected ? '#000' : '#FFF';
        const LabelGlyph = () => {
          if (variant === 'midnight') {
            return <path d="M20 18 a5 5 0 1 0 5 -5 a4 4 0 1 1 -5 5" fill={fg} stroke={fg} strokeWidth="0.5" />;
          }
          if (variant === 'mystery') {
            return (
              <text x="21" y="24"
                fontFamily="Chicago, 'Trebuchet MS', sans-serif"
                fontSize="9" fontWeight="bold" textAnchor="middle" fill={fg}>?</text>
            );
          }
          return (
            <g stroke={fg} strokeWidth="1" fill="none">
              <rect x="17" y="19" width="7" height="6" rx="1" fill={fg} stroke={fg} strokeWidth="0.5" />
              <line x1="17" y1="19" x2="16" y2="14" strokeLinecap="round" />
              <line x1="19" y1="18" x2="18" y2="13" strokeLinecap="round" />
              <line x1="21" y1="18" x2="21" y2="13" strokeLinecap="round" />
              <line x1="23" y1="19" x2="24" y2="14" strokeLinecap="round" />
              <line x1="17" y1="21" x2="14" y2="20" strokeLinecap="round" />
            </g>
          );
        };
        return (
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
               xmlns="http://www.w3.org/2000/svg" className="ee-floppy-icon">
            <rect x="2" y="2" width="28" height="28" rx="2" fill={bg} stroke={fg} strokeWidth="2" />
            <rect x="6" y="2" width="16" height="9" fill={fg} stroke={fg} strokeWidth="1" />
            <rect x="9" y="4" width="10" height="5" fill={bg} />
            <rect x="2" y="4" width="4" height="4" fill={bg} stroke={fg} strokeWidth="1" />
            <rect x="5" y="13" width="22" height="15" fill={bg} stroke={fg} strokeWidth="1" />
            <LabelGlyph />
          </svg>
        );
      };

            // --- NEW: Standalone Splash Screen SVGs ---
      const TrailKitSplashSVG = () => (
                <svg width="100%" height="100%" viewBox="0 0 400 250" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ display: 'block' }}>
          {/* Background & Frame */}
          <rect width="400" height="250" fill="#FFF"/>
          <rect x="2" y="2" width="396" height="246" fill="#FFF" stroke="#000" strokeWidth="4"/>
          <rect x="8" y="8" width="384" height="234" fill="none" stroke="#000" strokeWidth="1"/>

          {/* Dithered Sky Gradient */}
          <g stroke="#000" strokeWidth="1">
            <line x1="10" y1="15" x2="390" y2="15" strokeDasharray="1 7"/>
            <line x1="10" y1="20" x2="390" y2="20" strokeDasharray="1 5"/>
            <line x1="10" y1="25" x2="390" y2="25" strokeDasharray="1 3"/>
            <line x1="10" y1="30" x2="390" y2="30" strokeDasharray="1 2"/>
            <line x1="10" y1="35" x2="390" y2="35" strokeDasharray="2 2"/>
            <line x1="10" y1="40" x2="390" y2="40" strokeDasharray="3 2"/>
            <line x1="10" y1="45" x2="390" y2="45" strokeDasharray="4 2"/>
            <line x1="10" y1="50" x2="390" y2="50" strokeDasharray="5 2"/>
            <line x1="10" y1="55" x2="390" y2="55" strokeDasharray="10 2"/>
          </g>

          {/* Giant Mac OS Sun */}
          <circle cx="200" cy="120" r="70" fill="#FFF" stroke="#000" strokeWidth="2" strokeDasharray="4 2"/>
          <circle cx="200" cy="120" r="60" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="2 4"/>

          {/* Background Mountains & Heavy Shading */}
          <path d="M 50 240 L 150 120 L 250 240 Z" fill="#FFF" stroke="#000" strokeWidth="2" strokeLinejoin="round"/>
          <path d="M 150 120 L 150 240 L 250 240 Z" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="1 2"/>
          <path d="M 150 120 L 130 150 L 150 160 L 140 180 L 160 190 L 150 210 L 170 240 L 150 240 Z" fill="#000"/>

          <path d="M 220 240 L 320 90 L 410 240 Z" fill="#FFF" stroke="#000" strokeWidth="2" strokeLinejoin="round"/>
          <path d="M 320 90 L 300 130 L 320 140 L 310 160 L 330 180 L 320 210 L 340 240 L 320 240 Z" fill="#000"/>

          <path d="M -20 240 L 70 140 L 140 240 Z" fill="#FFF" stroke="#000" strokeWidth="2" strokeLinejoin="round"/>
          <path d="M 70 140 L 50 170 L 70 180 L 60 210 L 80 240 L 70 240 Z" fill="#000"/>

          {/* Foreground Trail */}
          <path d="M 8 240 L 8 180 Q 100 190 200 210 T 392 190 L 392 240 Z" fill="#FFF" stroke="#000" strokeWidth="2"/>
          <path d="M 8 190 Q 100 200 200 220 T 392 200" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="1 4"/>
          <path d="M 8 200 Q 100 210 200 230 T 392 210" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="1 2"/>

          {/* Pine Trees */}
          <g stroke="#000" strokeWidth="1" strokeLinejoin="round">
            <path d="M 40 220 L 40 200 L 25 200 L 40 170 L 30 170 L 45 140 L 60 170 L 50 170 L 65 200 L 50 200 L 50 220 Z" fill="#FFF"/>
            <path d="M 45 140 L 45 220 M 45 170 L 60 170 L 50 170 L 65 200 L 50 200 L 50 220" fill="none" strokeWidth="2"/>
            <path d="M 45 140 L 60 170 L 50 170 L 65 200 L 50 200" fill="#000"/>

            <path d="M 360 230 L 360 210 L 345 210 L 360 180 L 350 180 L 365 150 L 380 180 L 370 180 L 385 210 L 370 210 L 370 230 Z" fill="#FFF"/>
            <path d="M 365 150 L 380 180 L 370 180 L 385 210 L 370 210" fill="#000"/>
          </g>

          {/* App Title Plate */}
          <rect x="20" y="20" width="160" height="50" fill="#FFF" stroke="#000" strokeWidth="2" rx="4"/>
          <rect x="22" y="22" width="156" height="46" fill="none" stroke="#000" strokeWidth="1" rx="2"/>
          <text x="100" y="45" fontFamily="Chicago, 'Trebuchet MS', sans-serif" fontSize="20" fontWeight="bold" textAnchor="middle" fill="#000" letterSpacing="2">TRAILKIT</text>
        </svg>
      );

      const PlanFitSplashSVG = () => (
        <svg width="100%" height="100%" viewBox="0 0 400 250" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ display: 'block' }}>
          <defs>
            <pattern id="planfit-grid" width="20" height="20" patternUnits="userSpaceOnUse">
              <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#000" strokeWidth="0.5" strokeDasharray="2 2"/>
            </pattern>
            <pattern id="dither-dark" width="4" height="4" patternUnits="userSpaceOnUse">
              <rect x="0" y="0" width="2" height="2" fill="#000"/>
              <rect x="2" y="2" width="2" height="2" fill="#000"/>
            </pattern>
          </defs>

          <rect width="400" height="250" fill="#FFF"/>
          <rect x="8" y="8" width="384" height="234" fill="url(#planfit-grid)"/>
          <rect x="2" y="2" width="396" height="246" fill="none" stroke="#000" strokeWidth="4"/>
          <rect x="8" y="8" width="384" height="234" fill="none" stroke="#000" strokeWidth="1"/>

          <g transform="translate(30, 80)">
            <rect x="0" y="0" width="140" height="180" fill="#FFF" stroke="#000" strokeWidth="2"/>
            <rect x="-4" y="10" width="140" height="180" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="1 2"/>
            <rect x="40" y="-10" width="60" height="20" fill="#FFF" stroke="#000" strokeWidth="2" rx="4"/>
            <circle cx="70" cy="0" r="4" fill="#000"/>
            
            <g stroke="#000" strokeWidth="1.5">
              <rect x="15" y="30" width="12" height="12" fill="#FFF"/>
              <path d="M 12 35 L 18 40 L 30 25" strokeWidth="2" fill="none"/>
              <line x1="35" y1="36" x2="120" y2="36" strokeDasharray="2 2"/>

              <rect x="15" y="55" width="12" height="12" fill="#FFF"/>
              <path d="M 12 60 L 18 65 L 30 50" strokeWidth="2" fill="none"/>
              <line x1="35" y1="61" x2="120" y2="61" strokeDasharray="2 2"/>

              <rect x="15" y="80" width="12" height="12" fill="#FFF"/>
              <line x1="35" y1="86" x2="120" y2="86" strokeDasharray="2 2"/>
              
              <rect x="15" y="105" width="12" height="12" fill="#FFF"/>
              <line x1="35" y1="111" x2="120" y2="111" strokeDasharray="2 2"/>
            </g>
          </g>

          <g transform="translate(250, 130)">
            <circle cx="0" cy="0" r="70" fill="#FFF" stroke="#000" strokeWidth="3"/>
            <circle cx="0" cy="0" r="62" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="4 4"/>
            <circle cx="0" cy="0" r="54" fill="none" stroke="#000" strokeWidth="1"/>
            
            <rect x="-12" y="-85" width="24" height="15" fill="#FFF" stroke="#000" strokeWidth="2"/>
            <rect x="-16" y="-95" width="32" height="10" fill="#FFF" stroke="#000" strokeWidth="2" rx="2"/>
            
            <g transform="rotate(45)">
              <rect x="-8" y="-82" width="16" height="12" fill="#FFF" stroke="#000" strokeWidth="2"/>
              <rect x="-12" y="-88" width="24" height="6" fill="#FFF" stroke="#000" strokeWidth="2" rx="1"/>
            </g>

            <g transform="rotate(-45)">
              <rect x="-8" y="-82" width="16" height="12" fill="#FFF" stroke="#000" strokeWidth="2"/>
              <rect x="-12" y="-88" width="24" height="6" fill="#FFF" stroke="#000" strokeWidth="2" rx="1"/>
            </g>

            <circle cx="0" cy="0" r="4" fill="#000"/>
            <path d="M 0 0 L 25 -35" fill="none" stroke="#000" strokeWidth="3" strokeLinecap="round"/>
            <path d="M 0 0 L -15 -10" fill="none" stroke="#000" strokeWidth="2" strokeLinecap="round"/>
            
            <circle cx="0" cy="25" r="15" fill="#FFF" stroke="#000" strokeWidth="1"/>
            <path d="M 0 25 L 0 15" fill="none" stroke="#000" strokeWidth="1.5"/>
          </g>

          <g transform="translate(160, 200) rotate(-15)">
            <rect x="0" y="-5" width="120" height="10" fill="#FFF" stroke="#000" strokeWidth="2"/>
            <path d="M 0 -5 L 120 -5 L 120 5 L 0 5 Z" fill="url(#dither-dark)"/>
            
            <polygon points="20,-25 0,-15 0,15 20,25" fill="#FFF" stroke="#000" strokeWidth="2"/>
            <polygon points="20,-25 0,-15 0,15 20,25" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="1 2"/>
            <polygon points="30,-30 20,-25 20,25 30,30" fill="#000" stroke="#000" strokeWidth="2"/>
            
            <polygon points="100,-25 120,-15 120,15 100,25" fill="#FFF" stroke="#000" strokeWidth="2"/>
            <polygon points="100,-25 120,-15 120,15 100,25" fill="none" stroke="#000" strokeWidth="1" strokeDasharray="1 2"/>
            <polygon points="90,-30 100,-25 100,25 90,30" fill="#000" stroke="#000" strokeWidth="2"/>
          </g>

          <rect x="20" y="20" width="160" height="50" fill="#FFF" stroke="#000" strokeWidth="2" rx="4"/>
          <rect x="22" y="22" width="156" height="46" fill="url(#dither-dark)"/>
          <rect x="25" y="25" width="150" height="40" fill="#FFF" stroke="#000" strokeWidth="1"/>
          <text x="100" y="45" fontFamily="sans-serif" fontSize="24" fontWeight="bold" textAnchor="middle" dominantBaseline="middle" fill="#000" letterSpacing="2">PLANFIT</text>
        </svg>
      );


      // EASTER EGG: Icon Physics — helper components
      const PoofCloud = ({ x, y }) => (
        <div className="ee-poof-cloud"
             style={{ left: x - 28, top: y - 28, width: 56, height: 56 }}>
          <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M28 48 C18 48 10 42 10 34 C10 30 12 27 15 25 C14 23 13 21 13 19 C13 14 17 10 22 10 C23 7 26 5 28 5 C30 5 33 7 34 10 C39 10 43 14 43 19 C43 21 42 23 41 25 C44 27 46 30 46 34 C46 42 38 48 28 48Z"
              fill="#FFF" stroke="#000" strokeWidth="2" strokeLinejoin="round"/>
          </svg>
        </div>
      );

      const SmugFace = ({ fading }) => (
        <div className={`ee-smug-face${fading ? ' ee-smug-fadeout' : ''}`}>
          <svg width="18" height="14" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="0" y="0" width="18" height="14" fill="#FFF" stroke="#000" strokeWidth="1.5" rx="2"/>
            <circle cx="5" cy="5" r="1.5" fill="#000"/>
            <circle cx="13" cy="5" r="1.5" fill="#000"/>
            <path d="M5 9 Q9 12 13 9" stroke="#000" strokeWidth="1.5" fill="none" strokeLinecap="round"/>
          </svg>
        </div>
      );

      const DizzyStars = () => (
        <div className="ee-dizzy-stars">
          {[0, 1, 2].map(i => (
            <div key={i} className="ee-dizzy-star">
              <svg width="8" height="8" viewBox="0 0 8 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 0 L5 3 L8 4 L5 5 L4 8 L3 5 L0 4 L3 3 Z" fill="#000"/>
              </svg>
            </div>
          ))}
        </div>
      );

      const AppleLogo = () => (
        <img 
          src="https://tgeddes.com/images/favicon-32x32.png" 
          alt="Menu Logo" 
          style={{ width: '16px', height: '16px', objectFit: 'contain' }} 
        />
      );

      // Moved completely outside App to preserve state between renders
      const DesktopIcon = ({
        item, isDesktop, selectedIcon, setSelectedIcon, openWindow,
        // Easter egg: Icon Physics props
        poofedIds, smugIds, smugFadingIds, dizzyIds,
        onTrashMove, onTrashRelease, onShake,
      }) => {
        const isSelected = selectedIcon === item.id;
        const [pos, setPos] = useState({ x: item.x || 0, y: item.y || 0 });
        const [tipped, setTipped] = useState(false);
        const dragOffset = useRef({ x: 0, y: 0 });
        const shakeRef = useRef({ lastX: null, direction: 0, reversals: 0, windowStart: 0 });

        useEffect(() => {
          setPos({ x: item.x || 0, y: item.y || 0 });
        }, [item.x, item.y]);

        const handlePointerDown = (e) => {
          e.stopPropagation();
          setSelectedIcon(item.id);
          if (isDesktop) {
            e.currentTarget.setPointerCapture(e.pointerId);
            dragOffset.current = { x: e.clientX - pos.x, y: e.clientY - pos.y };
            shakeRef.current = { lastX: e.clientX, direction: 0, reversals: 0, windowStart: Date.now() };
          }
        };

        const handlePointerMove = (e) => {
          if (!isDesktop) return;
          if (!e.currentTarget.hasPointerCapture(e.pointerId)) return;

          const newX = e.clientX - dragOffset.current.x;
          const newY = e.clientY - dragOffset.current.y;
          setPos({ x: newX, y: newY });

          if (item.type === 'trash' && onTrashMove) {
            onTrashMove({ left: newX, top: newY, right: newX + 64, bottom: newY + 64 });
          }

          // Shake detection
          const shake = shakeRef.current;
          const dx = e.clientX - shake.lastX;
          if (Math.abs(dx) > 4) {
            const newDir = dx > 0 ? 1 : -1;
            if (shake.direction !== 0 && newDir !== shake.direction) {
              const now = Date.now();
              if (now - shake.windowStart > 600) {
                shake.reversals = 1;
                shake.windowStart = now;
              } else {
                shake.reversals += 1;
              }
              if (shake.reversals >= 4) {
                onShake && onShake(item.id);
                shake.reversals = 0;
                shake.windowStart = Date.now();
              }
            }
            shake.direction = newDir;
            shake.lastX = e.clientX;
          }
        };

        const handlePointerUp = (e) => {
          if (isDesktop && e.currentTarget.hasPointerCapture(e.pointerId)) {
            e.currentTarget.releasePointerCapture(e.pointerId);
            if (item.type === 'trash' && onTrashRelease) {
              const newX = e.clientX - dragOffset.current.x;
              const newY = e.clientY - dragOffset.current.y;
              onTrashRelease({ left: newX, top: newY, right: newX + 64, bottom: newY + 64 });
            }
          }
        };

        const handleDoubleClick = (e) => {
          e.stopPropagation();
          if (item.type === 'trash') {
            SoundSystem.playSosumi();
            setTipped(!tipped);
            return;
          }
          openWindow(item);
        };

        const isPoofly = poofedIds && poofedIds.has(item.id);
        const isSmug   = smugIds && smugIds.has(item.id);
        const isFading = smugFadingIds && smugFadingIds.has(item.id);
        const isDizzy  = dizzyIds && dizzyIds.has(item.id);

        return (
          <div
            className={`mac-icon-container ${isSelected ? 'selected' : ''}${isDizzy ? ' ee-dizzy-icon' : ''}`}
            style={
              isDesktop
                ? { left: pos.x, top: pos.y, visibility: isPoofly ? 'hidden' : 'visible' }
                : { position: 'relative', margin: '15px', display: 'inline-flex' }
            }
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerUp}
            onPointerCancel={handlePointerUp}
            onDoubleClick={handleDoubleClick}
          >
            {isDizzy && <DizzyStars />}
            {item.type === 'folder' && <FolderIcon selected={isSelected} />}
            {item.type === 'file' && <FileIcon selected={isSelected} />}
            {item.type === 'trash' && <TrashIcon selected={isSelected} tipped={tipped} />}
            {item.type === 'floppy' && <FloppyIcon selected={isSelected} variant={item.variant} />}
            {item.id === 'app-trailkit' && <TKIcon selected={isSelected} />}
            {item.id === 'app-planfit' && <PFIcon selected={isSelected} />}
            <div className="mac-icon-label">{item.name}</div>
            {(isSmug || isFading) && <SmugFace fading={isFading} />}
          </div>
        );
      };

      // Moved completely outside App to preserve drag/resize state safely
      const DraggableWindow = ({ win, bringToFront, closeWindow, handleDesktopClick, selectedIcon, setSelectedIcon, openWindow, allWindows }) => {
        const [pos, setPos] = useState({ x: win.x, y: win.y });
        const [size, setSize] = useState({ w: win.width, h: win.height });
        const dragOffset = useRef({ x: 0, y: 0 });
        const resizeStart = useRef({ w: 0, h: 0, x: 0, y: 0 });

        const handleTitlePointerDown = (e) => {
          e.stopPropagation();
          bringToFront(win.id);
          e.currentTarget.setPointerCapture(e.pointerId);
          dragOffset.current = { x: e.clientX - pos.x, y: e.clientY - pos.y };
        };

        const handleTitlePointerMove = (e) => {
          if (e.currentTarget.hasPointerCapture(e.pointerId)) {
            setPos({ x: e.clientX - dragOffset.current.x, y: e.clientY - dragOffset.current.y });
          }
        };

        const handleTitlePointerUp = (e) => {
          if (e.currentTarget.hasPointerCapture(e.pointerId)) {
            e.currentTarget.releasePointerCapture(e.pointerId);
          }
        };

        const handleResizePointerDown = (e) => {
          e.stopPropagation();
          bringToFront(win.id);
          e.currentTarget.setPointerCapture(e.pointerId);
          resizeStart.current = { w: size.w, h: size.h, x: e.clientX, y: e.clientY };
        };

        const handleResizePointerMove = (e) => {
          if (e.currentTarget.hasPointerCapture(e.pointerId)) {
            const newW = Math.max(200, resizeStart.current.w + (e.clientX - resizeStart.current.x));
            const newH = Math.max(150, resizeStart.current.h + (e.clientY - resizeStart.current.y));
            setSize({ w: newW, h: newH });
          }
        };

        const handleResizePointerUp = (e) => {
          if (e.currentTarget.hasPointerCapture(e.pointerId)) {
            e.currentTarget.releasePointerCapture(e.pointerId);
          }
        };

        return (
          <div 
            className="mac-window"
            style={{ left: pos.x, top: pos.y, zIndex: win.zIndex, width: size.w, height: size.h }}
            onPointerDown={(e) => { e.stopPropagation(); bringToFront(win.id); }}
          >
            <div 
              className="mac-titlebar" 
              onPointerDown={handleTitlePointerDown}
              onPointerMove={handleTitlePointerMove}
              onPointerUp={handleTitlePointerUp}
              onPointerCancel={handleTitlePointerUp}
            >
              <div className="mac-close-box" onPointerDown={(e) => { e.stopPropagation(); closeWindow(win.id); }}></div>
              <div className="mac-titlebar-text">{win.name}</div>
            </div>
            
            <div className="mac-window-content" onPointerDown={handleDesktopClick}>
              {win.type === 'aboutmac' ? (
                <AboutMacContent windows={(allWindows || []).filter(w => w.id !== 'about-this-mac')} />
              ) : win.type === 'text' ? (
                <div style={{ padding: '10px', fontSize: '14px', lineHeight: '1.5' }}>
                  {win.content}
                </div>
              ) : win.type === 'iframe' ? (
                <iframe src={win.url} style={{ width: '100%', height: '100%', border: 'none', display: 'block' }} title={win.name}></iframe>
              ) : win.type === 'splash' ? (
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%' }}>
                  {win.id === 'trailkit-splash' ? <TrailKitSplashSVG /> : <PlanFitSplashSVG />}
                </div>
              ) : (
                <div style={{ display: 'flex', flexWrap: 'wrap', alignContent: 'flex-start' }}>
                  {win.children && win.children.map(child => (
                    <DesktopIcon key={child.id} item={child} isDesktop={false} selectedIcon={selectedIcon} setSelectedIcon={setSelectedIcon} openWindow={openWindow} />
                  ))}
                  {(!win.children || win.children.length === 0) && (
                    <div style={{ color: '#666', width: '100%', textAlign: 'center', marginTop: '20px' }}>
                      0 items
                    </div>
                  )}
                </div>
              )}
            </div>

            <div 
              className="mac-resize-handle" 
              onPointerDown={handleResizePointerDown}
              onPointerMove={handleResizePointerMove}
              onPointerUp={handleResizePointerUp}
              onPointerCancel={handleResizePointerUp}
            >
              <svg width="10" height="10" viewBox="0 0 10 10">
                <rect x="6" y="6" width="4" height="4" fill="black" />
                <rect x="2" y="6" width="4" height="4" fill="black" />
                <rect x="6" y="2" width="4" height="4" fill="black" />
              </svg>
            </div>
          </div>
        );
      };

      // ================================================================
      // EASTER EGG: System Bomb dialog
      // ================================================================
      const BombSVG = () => (
        <svg width="52" height="56" viewBox="0 0 52 56" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M26 10 Q32 2 38 6 Q44 10 40 2" stroke="#000" strokeWidth="2" fill="none" strokeLinecap="round"/>
          <line x1="40" y1="2" x2="37" y2="0" stroke="#000" strokeWidth="1.5" strokeLinecap="round"/>
          <line x1="40" y1="2" x2="43" y2="0" stroke="#000" strokeWidth="1.5" strokeLinecap="round"/>
          <line x1="40" y1="2" x2="42" y2="4" stroke="#000" strokeWidth="1.5" strokeLinecap="round"/>
          <line x1="40" y1="2" x2="38" y2="5" stroke="#000" strokeWidth="1.5" strokeLinecap="round"/>
          <circle cx="22" cy="32" r="20" fill="#000" stroke="#000" strokeWidth="2"/>
          <rect x="21" y="10" width="4" height="6" rx="2" fill="#000" stroke="#000" strokeWidth="1"/>
        </svg>
      );

      const SystemBombDialog = ({ onRestart, onDismiss }) => {
        useEffect(() => {
          const handleKeyDown = (e) => { if (e.key === 'Escape') onDismiss(); };
          window.addEventListener('keydown', handleKeyDown);
          return () => window.removeEventListener('keydown', handleKeyDown);
        }, [onDismiss]);

        return (
          <div className="ee-bomb-overlay">
            <div className="ee-bomb-dialog" onPointerDown={(e) => e.stopPropagation()}>
              <div className="ee-bomb-body">
                <div className="ee-bomb-svg-wrap"><BombSVG /></div>
                <div className="ee-bomb-text">
                  <p>Sorry, a system error occurred.</p>
                  <p className="ee-bomb-id">ID = 02</p>
                </div>
              </div>
              <div className="ee-bomb-divider"></div>
              <div className="ee-bomb-buttons">
                <button className="ee-bomb-btn" onPointerDown={(e) => { e.stopPropagation(); onRestart(); }}>Restart</button>
                <button className="ee-bomb-btn ee-bomb-btn-disabled" disabled>Resume</button>
              </div>
            </div>
          </div>
        );
      };

      // ================================================================
      // EASTER EGG: About This Macintosh memory bar
      // ================================================================
      const WIN_PATTERNS = [
        'ee-aboutmac-seg-win0', 'ee-aboutmac-seg-win1', 'ee-aboutmac-seg-win2',
        'ee-aboutmac-seg-win3', 'ee-aboutmac-seg-win4', 'ee-aboutmac-seg-win5',
      ];

      const AboutMacContent = ({ windows }) => {
        const TOTAL_KB = 8192;
        const SYSTEM_KB = 1024;
        const openWins = windows || [];
        const perWindowKB = openWins.length > 0
          ? Math.floor((TOTAL_KB - SYSTEM_KB) * 0.6 / Math.max(openWins.length, 1))
          : 0;
        const usedByWins = perWindowKB * openWins.length;
        const unusedKB = TOTAL_KB - SYSTEM_KB - usedByWins;
        const segments = [
          { key: 'system', label: 'System Software', kb: SYSTEM_KB, cls: 'ee-aboutmac-seg-system', pct: (SYSTEM_KB / TOTAL_KB) * 100 },
          ...openWins.map((w, i) => ({
            key: w.id, label: w.name, kb: perWindowKB,
            cls: WIN_PATTERNS[i % WIN_PATTERNS.length], pct: (perWindowKB / TOTAL_KB) * 100,
          })),
          { key: 'unused', label: 'Largest Unused Block', kb: unusedKB, cls: 'ee-aboutmac-seg-unused', pct: (unusedKB / TOTAL_KB) * 100 },
        ];

        return (
          <div className="ee-aboutmac-content">
            <div className="ee-aboutmac-header">
              <svg width="32" height="38" viewBox="0 0 32 38" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ flexShrink: 0 }}>
                <path d="M16 4 C16 4 11 0 6 3 C1 6 2 13 5 16 C7 19 9 22 12 22 C13 22 14 21 16 21 C18 21 19 22 20 22 C23 22 25 19 27 16 C30 13 31 6 26 3 C21 0 16 4 16 4Z" fill="#000"/>
                <path d="M16 4 C16 2 17 0 19 0" stroke="#000" strokeWidth="1.5" fill="none" strokeLinecap="round"/>
              </svg>
              <div className="ee-aboutmac-header-text">
                <p className="ee-aboutmac-sysname">System Software 7.1</p>
                <p className="ee-aboutmac-copyright">© Tyler Geddes 1984–2026</p>
              </div>
            </div>
            <div className="ee-aboutmac-divider"></div>
            <div className="ee-aboutmac-specs">
              <div className="ee-aboutmac-spec-row"><span>Built-in Memory :</span><span className="ee-aboutmac-spec-value">8,192K</span></div>
              <div className="ee-aboutmac-spec-row"><span>Total Memory :</span><span className="ee-aboutmac-spec-value">8,192K</span></div>
            </div>
            <div className="ee-aboutmac-divider"></div>
            <div className="ee-aboutmac-membar-section">
              <div className="ee-aboutmac-membar-label">Memory Usage</div>
              <div className="ee-aboutmac-membar">
                {segments.map(seg => (
                  <div key={seg.key} className={`ee-aboutmac-membar-seg ${seg.cls}`}
                    style={{ width: `${seg.pct}%` }}
                    title={`${seg.label}: ${seg.kb.toLocaleString()}K`} />
                ))}
              </div>
              <div className="ee-aboutmac-membar-legend">
                {segments.map(seg => (
                  <div key={seg.key} className="ee-aboutmac-legend-item" style={{ width: `${seg.pct}%` }}>
                    <span className="ee-aboutmac-legend-name">{seg.label}</span>
                    <span className="ee-aboutmac-legend-kb">{seg.kb.toLocaleString()}K</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        );
      };

      // ================================================================
      // EASTER EGG: Flying Toasters (After Dark screensaver)
      // ================================================================
      const ToasterSVG = () => (
        <svg width="48" height="40" viewBox="0 0 48 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <g className="ee-toaster-wing-left">
            <polygon points="0,18 14,12 14,24 2,28" fill="none" stroke="#fff" strokeWidth="1.5" strokeLinejoin="round"/>
            <line x1="4"  y1="24" x2="14" y2="14" stroke="#fff" strokeWidth="1"/>
            <line x1="8"  y1="26" x2="14" y2="17" stroke="#fff" strokeWidth="1"/>
            <line x1="11" y1="27" x2="14" y2="21" stroke="#fff" strokeWidth="1"/>
          </g>
          <g className="ee-toaster-wing-right">
            <polygon points="48,18 34,12 34,24 46,28" fill="none" stroke="#fff" strokeWidth="1.5" strokeLinejoin="round"/>
            <line x1="44" y1="24" x2="34" y2="14" stroke="#fff" strokeWidth="1"/>
            <line x1="40" y1="26" x2="34" y2="17" stroke="#fff" strokeWidth="1"/>
            <line x1="37" y1="27" x2="34" y2="21" stroke="#fff" strokeWidth="1"/>
          </g>
          <rect x="12" y="10" width="24" height="22" rx="2" fill="none" stroke="#fff" strokeWidth="2"/>
          <rect x="16" y="10" width="7" height="4" rx="1" fill="none" stroke="#fff" strokeWidth="1.5"/>
          <rect x="25" y="10" width="7" height="4" rx="1" fill="none" stroke="#fff" strokeWidth="1.5"/>
          <line x1="12" y1="24" x2="36" y2="24" stroke="#fff" strokeWidth="1"/>
          <rect x="35" y="18" width="4" height="5" rx="1" fill="none" stroke="#fff" strokeWidth="1.5"/>
          <rect x="14" y="32" width="3" height="3" fill="none" stroke="#fff" strokeWidth="1.5"/>
          <rect x="31" y="32" width="3" height="3" fill="none" stroke="#fff" strokeWidth="1.5"/>
        </svg>
      );

      const ToastSVG = () => (
        <svg width="28" height="34" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M4,32 L4,10 Q4,2 14,2 Q24,2 24,10 L24,32 Z" fill="none" stroke="#fff" strokeWidth="2" strokeLinejoin="round"/>
          <line x1="4" y1="30" x2="24" y2="30" stroke="#fff" strokeWidth="1.5"/>
          <rect x="6"  y="8"  width="2" height="2" fill="#fff"/>
          <rect x="10" y="6"  width="2" height="2" fill="#fff"/>
          <rect x="15" y="6"  width="2" height="2" fill="#fff"/>
          <rect x="20" y="8"  width="2" height="2" fill="#fff"/>
          <rect x="8"  y="13" width="2" height="2" fill="#fff"/>
          <rect x="13" y="12" width="2" height="2" fill="#fff"/>
          <rect x="18" y="13" width="2" height="2" fill="#fff"/>
          <rect x="6"  y="19" width="2" height="2" fill="#fff"/>
          <rect x="11" y="20" width="2" height="2" fill="#fff"/>
          <rect x="17" y="19" width="2" height="2" fill="#fff"/>
          <rect x="21" y="18" width="2" height="2" fill="#fff"/>
          <line x1="6"  y1="28" x2="10" y2="24" stroke="#fff" strokeWidth="1" opacity="0.6"/>
          <line x1="10" y1="28" x2="16" y2="22" stroke="#fff" strokeWidth="1" opacity="0.6"/>
          <line x1="16" y1="28" x2="22" y2="22" stroke="#fff" strokeWidth="1" opacity="0.6"/>
        </svg>
      );

      const ToasterOverlay = ({ entities, onDismiss }) => (
        <div className="ee-toaster-overlay" onPointerDown={onDismiss}>
          {entities.map((e, i) => (
            <div key={i} className="ee-toaster-entity" style={{
              top: e.startTop, left: e.startLeft,
              '--ee-scale': e.scale,
              animationDuration: e.duration, animationDelay: e.delay,
            }}>
              {e.kind === 'toaster' ? <ToasterSVG /> : <ToastSVG />}
            </div>
          ))}
        </div>
      );

      // ================================================================
      // SCREENSAVER: Starfield warp (the Windows "fly through space"
      // effect, in glorious 1-bit black and white). Defined outside App
      // so its canvas/rAF loop isn't torn down on every parent render.
      // ================================================================
      const StarfieldScreensaver = ({ onDismiss }) => {
        const canvasRef = useRef(null);
        useEffect(() => {
          const canvas = canvasRef.current;
          if (!canvas) return;
          const ctx = canvas.getContext('2d');
          let w, h, cx, cy;
          const resize = () => {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
            cx = w / 2; cy = h / 2;
          };
          resize();

          const STAR_COUNT = 450;
          const SPEED = 9;
          const stars = [];
          const spawn = (s) => {
            s.x = (Math.random() * 2 - 1) * w;
            s.y = (Math.random() * 2 - 1) * h;
            s.z = Math.random() * w;
            s.pz = s.z;
          };
          for (let i = 0; i < STAR_COUNT; i++) { const s = {}; spawn(s); stars.push(s); }

          let raf;
          const draw = () => {
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, w, h);
            ctx.strokeStyle = '#fff';
            for (const s of stars) {
              s.pz = s.z;
              s.z -= SPEED;
              if (s.z < 1) { spawn(s); continue; }
              const sx = cx + (s.x / s.z) * w;
              const sy = cy + (s.y / s.z) * h;
              // off-screen? recycle so streaks stay near the field
              if (sx < 0 || sx > w || sy < 0 || sy > h) { spawn(s); continue; }
              const px = cx + (s.x / s.pz) * w;
              const py = cy + (s.y / s.pz) * h;
              ctx.lineWidth = Math.max(0.5, (1 - s.z / w) * 2.6);
              ctx.beginPath();
              ctx.moveTo(px, py);
              ctx.lineTo(sx, sy);
              ctx.stroke();
            }
            raf = requestAnimationFrame(draw);
          };
          draw();
          window.addEventListener('resize', resize);
          return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', resize);
          };
        }, []);
        return (
          <div className="ee-screensaver-overlay" onPointerDown={onDismiss}>
            <canvas ref={canvasRef}></canvas>
          </div>
        );
      };

      // ================================================================
      // EASTER EGG: Force Quit dialog
      // ================================================================
      const FQFolderIcon = ({ selected }) => (
        <svg width="14" height="12" viewBox="0 0 14 12" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M1 2H5L6 4H13V11H1V2Z"
            fill={selected ? '#fff' : '#000'}
            stroke={selected ? '#fff' : '#000'} strokeWidth="1.2" strokeLinejoin="miter"/>
        </svg>
      );

      const FQDocIcon = ({ selected }) => (
        <svg width="12" height="14" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M2 1H7L10 4V13H2V1Z"
            fill={selected ? '#000' : '#fff'}
            stroke={selected ? '#fff' : '#000'} strokeWidth="1.5" strokeLinejoin="miter"/>
          <path d="M7 1V4H10" stroke={selected ? '#fff' : '#000'} strokeWidth="1.5"/>
        </svg>
      );

      const ForceQuitModal = ({ windows, closeWindow, onClose }) => {
        const [selectedId, setSelectedId] = useState(null);
        const [subAlert, setSubAlert] = useState('');
        const subAlertTimer = useRef(null);

        const SYSTEM_PROCS = [
          { id: '__system__',      name: 'System',       isSystem: true },
          { id: '__multifinder__', name: 'MultiFinder',  isSystem: true },
          { id: '__finder__',      name: 'Finder',       isSystem: true },
        ];

        const allRows = [
          ...SYSTEM_PROCS,
          ...windows.map(w => ({ id: w.id, name: w.name, type: w.type, isSystem: false }))
        ];

        const handleForceQuit = () => {
          if (!selectedId) return;
          const row = allRows.find(r => r.id === selectedId);
          if (!row) return;
          if (row.isSystem) {
            clearTimeout(subAlertTimer.current);
            setSubAlert('You cannot quit System Software.');
            subAlertTimer.current = setTimeout(() => setSubAlert(''), 2500);
            return;
          }
          closeWindow(selectedId);
          const remaining = windows.filter(w => w.id !== selectedId);
          if (remaining.length === 0) onClose();
          else setSelectedId(null);
        };

        useEffect(() => { return () => clearTimeout(subAlertTimer.current); }, []);

        return (
          <>
            <div className="ee-forcequit-backdrop" onPointerDown={onClose} />
            <div className="ee-forcequit-modal" onPointerDown={(e) => e.stopPropagation()}>
              <div className="ee-forcequit-titlebar">
                <div className="ee-forcequit-titlebar-text">Force Quit Applications</div>
              </div>
              <div className="ee-forcequit-list">
                {allRows.map(row => {
                  const isSelected = selectedId === row.id;
                  return (
                    <div key={row.id}
                      className={`ee-forcequit-row${isSelected ? ' selected' : ''}${row.isSystem ? ' ee-forcequit-system' : ''}`}
                      onPointerDown={() => setSelectedId(row.id)}>
                      {row.type === 'folder'
                        ? <FQFolderIcon selected={isSelected} />
                        : <FQDocIcon selected={isSelected} />}
                      <span>{row.name}</span>
                    </div>
                  );
                })}
              </div>
              {subAlert && (<div className="ee-forcequit-subalert">{subAlert}</div>)}
              <div className="ee-forcequit-footer">
                <button className="ee-forcequit-btn" onPointerDown={onClose}>Cancel</button>
                <button className="ee-forcequit-btn ee-forcequit-btn-primary"
                  onPointerDown={handleForceQuit} disabled={!selectedId}>Force Quit</button>
              </div>
            </div>
          </>
        );
      };

      function App() {
        const [windows, setWindows] = useState([]);
        const [selectedIcon, setSelectedIcon] = useState(null);
        const [zIndexCounter, setZIndexCounter] = useState(10);
        const [desktopItems, setDesktopItems] = useState([]);
        const [appleMenuOpen, setAppleMenuOpen] = useState(false);
        const [appsMenuOpen, setAppsMenuOpen] = useState(false);
        const [toolsMenuOpen, setToolsMenuOpen] = useState(false);
        const [thoughtsMenuOpen, setThoughtsMenuOpen] = useState(false);
        const [isShuttingDown, setIsShuttingDown] = useState(false);

        // EASTER EGG: Menu Bar Mischief
        const [bombDialogOpen, setBombDialogOpen] = useState(false);
        const [bombRebooting, setBombRebooting] = useState(false);

        // EASTER EGG: Keyboard shortcuts
        const [toasterActive, setToasterActive] = useState(false);
        const [toasterEntities, setToasterEntities] = useState([]);
        const [screensaverActive, setScreensaverActive] = useState(false);
        const screensaverTimer = useRef(null);
        const idleSaverIndex = useRef(0);
        const keyBufferRef = useRef('');
        const [forceQuitOpen, setForceQuitOpen] = useState(false);

        // EASTER EGG: Icon Physics
        const [poofedIds, setPoofedIds] = useState(new Set());
        const [smugIds, setSmugIds] = useState(new Set());
        const [smugFadingIds, setSmugFadingIds] = useState(new Set());
        const [dizzyIds, setDizzyIds] = useState(new Set());
        const [poofAnimAt, setPoofAnimAt] = useState(null);
        const hoveredTargetIdRef = useRef(null);

        // EASTER EGG: Hidden Volumes (floppies)
        const [floppyWindowCount, setFloppyWindowCount] = useState(0);
        const [floppyIsMidnight, setFloppyIsMidnight] = useState(false);
        const [floppyIsIdle, setFloppyIsIdle] = useState(false);
        const floppyIdleTimer = useRef(null);

        // EASTER EGG: Sound
        const [soundEnabled, setSoundEnabled] = useState(true);
        const soundBootPlayed = useRef(false);

        // Environment guards: don't auto-open the production iframes locally,
        // and if we got rendered inside an iframe (e.g. the TrailKit Story
        // iframe accidentally loading index.php), stay quiet so the user
        // doesn't see an infinite recursive desktop.
        const IS_NESTED = (typeof window !== 'undefined') && (window.top !== window.self);
        const IS_PRODUCTION = (typeof location !== 'undefined') &&
                              location.hostname.endsWith('tgeddes.com');

        const closeAllMenus = () => {
          setAppleMenuOpen(false);
          setAppsMenuOpen(false);
          setToolsMenuOpen(false);
          setThoughtsMenuOpen(false);
        };

        const [clockTime, setClockTime] = useState(() => {
          const now = new Date();
          return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        });

        useEffect(() => {
          const tick = () => {
            const now = new Date();
            setClockTime(now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
          };
          const interval = setInterval(tick, 1000);
          return () => clearInterval(interval);
        }, []);

        // EASTER EGG: Sound — init from localStorage
        useEffect(() => {
          const stored = localStorage.getItem('ee-sound-enabled');
          if (stored !== null) {
            const val = stored !== 'false';
            setSoundEnabled(val);
            SoundSystem.enabled = val;
          }
        }, []);

        useEffect(() => {
          SoundSystem.enabled = soundEnabled;
        }, [soundEnabled]);

        // EASTER EGG: Midnight.disk — appear between 00:00 and 04:59
        useEffect(() => {
          if (IS_NESTED) return;
          const checkMidnight = () => {
            const h = new Date().getHours();
            setFloppyIsMidnight(h >= 0 && h <= 4);
          };
          checkMidnight();
          const intv = setInterval(checkMidnight, 30000);
          return () => clearInterval(intv);
        }, []);

        // EASTER EGG: Stowaway.disk — appear after 60s idle
        useEffect(() => {
          if (IS_NESTED) return;
          const IDLE_DELAY = 60000;
          const resetIdle = () => {
            setFloppyIsIdle(false);
            if (floppyIdleTimer.current) clearTimeout(floppyIdleTimer.current);
            floppyIdleTimer.current = setTimeout(() => setFloppyIsIdle(true), IDLE_DELAY);
          };
          resetIdle();
          window.addEventListener('mousemove', resetIdle, { passive: true });
          window.addEventListener('keydown', resetIdle, { passive: true });
          window.addEventListener('pointerdown', resetIdle, { passive: true });
          return () => {
            if (floppyIdleTimer.current) clearTimeout(floppyIdleTimer.current);
            window.removeEventListener('mousemove', resetIdle);
            window.removeEventListener('keydown', resetIdle);
            window.removeEventListener('pointerdown', resetIdle);
          };
        }, []);

        // SCREENSAVER: After 30s idle, alternate between the Starfield
        // warp and the Flying Toasters — each idle period flips to the
        // other. Any mouse/key/pointer activity dismisses whichever is
        // showing and restarts the countdown.
        useEffect(() => {
          if (IS_NESTED) return;
          const IDLE_DELAY = 30000;
          const showSaver = () => {
            if (idleSaverIndex.current % 2 === 0) {
              setScreensaverActive(true);
            } else {
              setToasterEntities(buildToasterEntities());
              setToasterActive(true);
            }
            idleSaverIndex.current += 1;
          };
          const resetIdle = () => {
            setScreensaverActive(false);
            setToasterActive(false);
            if (screensaverTimer.current) clearTimeout(screensaverTimer.current);
            screensaverTimer.current = setTimeout(showSaver, IDLE_DELAY);
          };
          resetIdle();
          window.addEventListener('mousemove', resetIdle, { passive: true });
          window.addEventListener('keydown', resetIdle, { passive: true });
          window.addEventListener('pointerdown', resetIdle, { passive: true });
          return () => {
            if (screensaverTimer.current) clearTimeout(screensaverTimer.current);
            window.removeEventListener('mousemove', resetIdle);
            window.removeEventListener('keydown', resetIdle);
            window.removeEventListener('pointerdown', resetIdle);
          };
        }, []);

        // EASTER EGG: Unified keyboard listener (typed "secret" + force-quit combo)
        useEffect(() => {
          if (IS_NESTED) return;
          const SECRET = 'secret';
          const BUFFER_LEN = SECRET.length;
          const handleKeyDown = (e) => {
            const isMac = /Mac|iPhone|iPad/.test(navigator.platform || '');
            const isForceQuitCombo = isMac
              ? (e.metaKey && e.altKey && e.key === 'Escape')
              : (e.ctrlKey && e.altKey && e.key === 'Escape');
            if (isForceQuitCombo) {
              e.preventDefault();
              setForceQuitOpen(prev => !prev);
              return;
            }
            if (toasterActive) {
              setToasterActive(false);
              keyBufferRef.current = '';
              return;
            }
            if (forceQuitOpen && e.key === 'Escape') {
              e.preventDefault();
              setForceQuitOpen(false);
              return;
            }
            const tag = document.activeElement?.tagName?.toLowerCase();
            const isEditable = tag === 'input' || tag === 'textarea' ||
                                document.activeElement?.isContentEditable;
            if (isEditable) return;
            if (e.key.length !== 1) return;
            keyBufferRef.current = (keyBufferRef.current + e.key.toLowerCase()).slice(-BUFFER_LEN);
            if (keyBufferRef.current === SECRET) {
              keyBufferRef.current = '';
              setToasterEntities(buildToasterEntities());
              setToasterActive(true);
            }
          };
          window.addEventListener('keydown', handleKeyDown);
          return () => window.removeEventListener('keydown', handleKeyDown);
        }, [toasterActive, forceQuitOpen]);

        useEffect(() => {
          fetch('?api=1')
            .then(response => response.json())
            .then(data => {
              const topOffset = window.innerHeight * 0.05;
              // Format the root folder to display as "Projects" if needed, and place it top-right
              const projectsFolder = {
                ...data,
                name: (data.name === 'Server Root' || data.id === 'root') ? 'Projects' : data.name,
                x: window.innerWidth - 80,
                y: topOffset + 20
              };

              setDesktopItems([
                projectsFolder, 
                { id: 'app-trailkit', name: 'TrailKit', type: 'app', url: 'https://tgeddes.com/projects/trailkit', x: window.innerWidth - 80, y: topOffset + 100, children: [] },
                { id: 'app-planfit', name: 'PlanFit', type: 'app', url: 'https://tgeddes.com/projects/planfit', x: window.innerWidth - 80, y: topOffset + 180, children: [] },
                { id: 'trash', name: 'Trash', type: 'trash', x: window.innerWidth - 80, y: window.innerHeight - 80, children: [] }
              ]);

              // Open default windows on load — only in production, and never
              // when nested inside another desktop's iframe.
              if (!IS_NESTED && IS_PRODUCTION) {
                setWindows([
                  {
                    id: 'trailkit-story',
                    name: 'TrailKit Story',
                    type: 'iframe',
                    url: 'trailkit/trailkitstory/index.html',
                    x: Math.max(50, window.innerWidth - 850),
                    y: 50,
                    zIndex: 10,
                    width: 725,
                    height: 750
                  },
                  {
                    id: 'about-tyler',
                    name: 'About Tyler Geddes',
                    type: 'text',
                    content: "By day, I’m a GTM Enablement leader, improving revenue metrics through cohesive global learning strategies and programs. By night, I’m an AI-native problem solver, building the software I’ve always wanted to use. I understand high-level SaaS execution and want to create the cutting-edge tools that make it actually work. I also just want to make some really cool, fun stuff.",
                    x: 50,
                    y: 50,
                    zIndex: 11,
                    width: 400,
                    height: 200
                  }
                ]);
                setZIndexCounter(11);
              }
            })
            .catch(error => console.error("Error fetching FS:", error));
          
          const handleResize = () => {
            setDesktopItems(items => items.map(item => {
              const topOffset = window.innerHeight * 0.05;
              if (item.id === 'trash') return { ...item, x: window.innerWidth - 80, y: window.innerHeight - 80 };
              if (item.id === 'root' || item.name === 'Projects') return { ...item, x: window.innerWidth - 80, y: topOffset + 20 };
              if (item.id === 'app-trailkit') return { ...item, x: window.innerWidth - 80, y: topOffset + 100 };
              if (item.id === 'app-planfit') return { ...item, x: window.innerWidth - 80, y: topOffset + 180 };
              return item;
            }));
          };
          window.addEventListener('resize', handleResize);
          return () => window.removeEventListener('resize', handleResize);
        }, []);

        const bringToFront = (id) => {
          setZIndexCounter(prev => {
            const newZ = prev + 1;
            setWindows(windowsPrev => windowsPrev.map(w => w.id === id ? { ...w, zIndex: newZ } : w));
            return newZ;
          });
        };

        const openWindow = (item) => {
          // EASTER EGG: floppy disks open a text window with a random message
          if (item.type === 'floppy') {
            const msgPool = {
              'floppy-midnight': floppyMidnightMessages,
              'floppy-mystery':  floppyMysteryMessages,
              'floppy-stowaway': floppyStowawayMessages,
            };
            const pool = msgPool[item.id] || [item.content];
            const msg = pool[Math.floor(Math.random() * pool.length)];
            item = { ...item, type: 'text', content: msg };
          }

          if (item.type === 'file' || item.type === 'app') {
            window.open(item.url, '_blank');
            return;
          }

          if (item.type === 'trash') {
            item = { ...item, type: 'folder', children: [] };
          }

          const existingWindow = windows.find(w => w.id === item.id);
          if (existingWindow) {
            bringToFront(item.id);
            return;
          }

          // EASTER EGG: Mystery.disk counter
          setFloppyWindowCount(prev => prev + 1);

          setZIndexCounter(prev => {
            const newZ = prev + 1;
            const isIframe = item.type === 'iframe';
            const newWindow = {
              ...item,
              x: 50 + (windows.length * 20),
              y: 50 + (windows.length * 20),
              zIndex: newZ,
              width: isIframe ? 725 : 450,
              height: isIframe ? 750 : 250
            };
            setWindows(windowsPrev => [...windowsPrev, newWindow]);
            return newZ;
          });
        };

        const closeWindow = (id) => {
          SoundSystem.playSosumi();
          setWindows(windows => windows.filter(w => w.id !== id));
        };

        const handleDesktopClick = (e) => {
          closeAllMenus();
          if (typeof e.target.className === 'string') {
            if (e.target.className.includes('mac-desktop') || e.target.className.includes('mac-window-content')) {
              setSelectedIcon(null);
            }
          }
        };

        const handleAboutClick = (e) => {
          e.stopPropagation();
          closeAllMenus();
          openWindow({
            id: 'about-tyler',
            name: 'About Tyler Geddes',
            type: 'text',
            content: "By day, I’m a GTM Enablement leader, improving revenue metrics through cohesive global learning strategies and programs. By night, I’m an AI-native problem solver, building the software I’ve always wanted to use. I understand high-level SaaS execution and want to create the cutting-edge tools that make it actually work. I also just want to make some really cool, fun stuff."
          });
        };

        const handleAboutTrailKit = (e) => {
          e.stopPropagation();
          closeAllMenus();
          openWindow({
            id: 'trailkit-splash',
            name: 'About TrailKit',
            type: 'splash',
            width: 400,
            height: 270
          });
        };

        const handleAboutPlanFit = (e) => {
          e.stopPropagation();
          closeAllMenus();
          openWindow({
            id: 'planfit-splash',
            name: 'About PlanFit',
            type: 'splash',
            width: 400,
            height: 270
          });
        };

        const handleBuildingTrailKit = (e) => {
          e.stopPropagation();
          closeAllMenus();
          openWindow({
            id: 'trailkit-story',
            name: 'TrailKit Story',
            type: 'iframe',
            url: 'trailkit/trailkitstory/index.html',
            width: 725,
            height: 750
          });
        };

        const handleJDExtractor = (e) => {
          e.stopPropagation();
          closeAllMenus();
          openWindow({
            id: 'jd-extractor',
            name: 'JD-Extractor',
            type: 'iframe',
            url: 'JD-Extractor/JD-Extractor.html',
            width: 725,
            height: 750
          });
        };

        const handleResumeCatalogue = (e) => {
          e.stopPropagation();
          closeAllMenus();
          openWindow({
            id: 'resume-catalogue',
            name: 'Resume Catalogue',
            type: 'iframe',
            url: 'Resume-Catalogue/Resume-Catalogue.html',
            width: 725,
            height: 750
          });
        };

        const handleShutDownClick = (e) => {
          e.stopPropagation();
          closeAllMenus();
          SoundSystem.playSosumi();
          setIsShuttingDown(true);
          setTimeout(() => {
            window.location.href = 'https://www.tgeddes.com';
          }, 1500);
        };

        // ============================================================
        // EASTER EGG handlers
        // ============================================================

        // --- Menu Bar Mischief ---
        const handleAppleLogoPointerDown = (e) => {
          e.stopPropagation();
          SoundSystem.playQuack();
          if (e.metaKey || e.ctrlKey) {
            closeAllMenus();
            setBombDialogOpen(true);
          } else {
            closeAllMenus();
            setAppleMenuOpen(!appleMenuOpen);
          }
        };

        const handleBombDismiss = () => setBombDialogOpen(false);

        const handleBombRestart = () => {
          setBombDialogOpen(false);
          setBombRebooting(true);
          setTimeout(() => {
            setWindows([]);
            setSelectedIcon(null);
            closeAllMenus();
            fetch('?api=1')
              .then(r => r.json())
              .then(data => {
                const topOffset = window.innerHeight * 0.05;
                const projectsFolder = {
                  ...data,
                  name: (data.name === 'Server Root' || data.id === 'root') ? 'Projects' : data.name,
                  x: window.innerWidth - 80,
                  y: topOffset + 20
                };
                setDesktopItems([
                  projectsFolder,
                  { id: 'app-trailkit', name: 'TrailKit', type: 'app', url: 'https://tgeddes.com/projects/trailkit', x: window.innerWidth - 80, y: topOffset + 100, children: [] },
                  { id: 'app-planfit', name: 'PlanFit', type: 'app', url: 'https://tgeddes.com/projects/planfit', x: window.innerWidth - 80, y: topOffset + 180, children: [] },
                  { id: 'trash', name: 'Trash', type: 'trash', x: window.innerWidth - 80, y: window.innerHeight - 80, children: [] }
                ]);
                setZIndexCounter(10);
                setBombRebooting(false);
              })
              .catch(() => setBombRebooting(false));
          }, 600);
        };

        const handleAboutThisMac = (e) => {
          e.stopPropagation();
          closeAllMenus();
          const existing = windows.find(w => w.id === 'about-this-mac');
          if (existing) { bringToFront('about-this-mac'); return; }
          setZIndexCounter(prev => {
            const newZ = prev + 1;
            setWindows(wp => [...wp, {
              id: 'about-this-mac',
              name: 'About This Macintosh',
              type: 'aboutmac',
              x: Math.max(30, Math.floor(window.innerWidth / 2) - 210),
              y: Math.max(30, Math.floor(window.innerHeight / 2) - 130),
              zIndex: newZ,
              width: 420,
              height: 260,
            }]);
            return newZ;
          });
        };

        // --- Keyboard shortcuts (Flying Toasters + Force Quit) ---
        const buildToasterEntities = () => {
          const entities = [];
          const count = 8;
          for (let i = 0; i < count; i++) {
            const isToast = i >= 6;
            const startTop  = `${Math.random() * 70}vh`;
            const startLeft = `${50 + Math.random() * 55}vw`;
            const scale     = 0.6 + Math.random() * 0.8;
            const duration  = `${8 + Math.random() * 10}s`;
            const delay     = `${-Math.random() * 12}s`;
            entities.push({ kind: isToast ? 'toast' : 'toaster', startTop, startLeft, scale, duration, delay });
          }
          return entities;
        };
        const dismissToasters = () => setToasterActive(false);
        const closeForceQuit = () => setForceQuitOpen(false);
        const dismissScreensaver = () => setScreensaverActive(false);
        const handleStartScreensaver = (e) => {
          e.stopPropagation();
          closeAllMenus();
          setScreensaverActive(true);
        };

        // --- Icon Physics ---
        const NON_POOF_TARGETS = new Set(['trash', 'root']);
        const boxesOverlap = (a, b) => {
          const MARGIN = 20;
          return (
            a.left   < b.right  - MARGIN &&
            a.right  > b.left   + MARGIN &&
            a.top    < b.bottom - MARGIN &&
            a.bottom > b.top    + MARGIN
          );
        };
        const handleTrashMove = (trashBounds) => {
          let found = null;
          for (const item of desktopItems) {
            if (item.type === 'trash') continue;
            if (NON_POOF_TARGETS.has(item.id)) continue;
            if (item.name === 'Projects') continue;
            const iconBounds = {
              left:   item.x,
              top:    item.y,
              right:  item.x + 64,
              bottom: item.y + 64 + 20,
            };
            if (boxesOverlap(trashBounds, iconBounds)) { found = item.id; break; }
          }
          hoveredTargetIdRef.current = found;
        };
        const handleTrashRelease = (trashBounds) => {
          const targetId = hoveredTargetIdRef.current;
          hoveredTargetIdRef.current = null;
          if (!targetId) return;
          const target = desktopItems.find(i => i.id === targetId);
          if (!target) return;
          const cx = target.x + 32;
          const cy = target.y + 32;
          setPoofAnimAt({ id: targetId, x: cx, y: cy });
          setPoofedIds(prev => new Set([...prev, targetId]));
          setTimeout(() => setPoofAnimAt(null), 600);
          setDesktopItems(items =>
            items.map(item => item.type === 'trash'
              ? { ...item, x: window.innerWidth - 80, y: window.innerHeight - 80 }
              : item
            )
          );
          setTimeout(() => {
            setPoofedIds(prev => { const n = new Set(prev); n.delete(targetId); return n; });
            setSmugIds(prev => new Set([...prev, targetId]));
            setTimeout(() => {
              setSmugIds(prev => { const n = new Set(prev); n.delete(targetId); return n; });
              setSmugFadingIds(prev => new Set([...prev, targetId]));
              setTimeout(() => {
                setSmugFadingIds(prev => { const n = new Set(prev); n.delete(targetId); return n; });
              }, 400);
            }, 3000);
          }, 2000);
        };
        const handleShake = (id) => {
          setDizzyIds(prev => {
            if (prev.has(id)) return prev;
            const n = new Set([...prev, id]);
            setTimeout(() => {
              setDizzyIds(p => { const m = new Set(p); m.delete(id); return m; });
            }, 3000);
            return n;
          });
        };

        // --- Sound toggle ---
        const handleSoundToggle = (e) => {
          e.stopPropagation();
          setSoundEnabled(prev => {
            const next = !prev;
            SoundSystem.enabled = next;
            localStorage.setItem('ee-sound-enabled', String(next));
            return next;
          });
        };

        // --- Floppy disk joke messages ---
        const floppyMidnightMessages = [
          "Midnight.disk — Still up building things nobody asked for? Respect. This is either your best work or tomorrow's git reset --hard. The threshold is famously unclear at 2am.",
          "You've unlocked Midnight.disk. Technically a feature. Realistically, go to sleep — your PRD will still be 40% vibes in the morning.",
          "It's the middle of the night and you're poking around a fake Mac OS 7 desktop. I'm not judging. I'm an easter egg. I can't judge. But I'm a little worried."
        ];
        const floppyMysteryMessages = [
          "Mystery.disk — You've opened 5 windows. That's not a workflow, that's an enablement strategy with no north star metric. Impressive.",
          "Five windows opened. Somewhere a product manager just felt a disturbance in the force and wrote a ticket about it.",
          "Mystery.disk unlocked at window #5. If each window is a different project, that's not multitasking — that's a portfolio. Which, honestly, checks out for this site."
        ];
        const floppyStowawayMessages = [
          "Stowaway.disk — You went idle for 60 seconds. I was just sitting here the whole time, waiting. I don't have anywhere else to be. I'm a floppy disk.",
          "60 seconds of inactivity detected. In GTM terms, your pipeline has stalled. In human terms: snack break. Either is valid.",
          "You went idle and I appeared. I've been here the whole time, technically — just not visible. Like most good documentation."
        ];

        // --- Floppy visibility (derived) ---
        const floppyVisibleItems = (() => {
          const items = [];
          const trashY = window.innerHeight - 80;
          const rightX = window.innerWidth - 80;
          let slotIndex = 0;
          if (floppyIsMidnight) {
            slotIndex++;
            items.push({ id: 'floppy-midnight', name: 'Midnight.disk', type: 'floppy', variant: 'midnight', x: rightX, y: trashY - (slotIndex * 80) });
          }
          if (floppyWindowCount >= 5) {
            slotIndex++;
            items.push({ id: 'floppy-mystery', name: 'Mystery.disk', type: 'floppy', variant: 'mystery', x: rightX, y: trashY - (slotIndex * 80) });
          }
          if (floppyIsIdle) {
            slotIndex++;
            items.push({ id: 'floppy-stowaway', name: 'Stowaway.disk', type: 'floppy', variant: 'stowaway', x: rightX, y: trashY - (slotIndex * 80) });
          }
          return items;
        })();

        return (
          <div className="mac-desktop" onPointerDown={(e) => {
            if (!IS_NESTED && !soundBootPlayed.current) {
              soundBootPlayed.current = true;
              SoundSystem.playBoot();
            }
            handleDesktopClick(e);
          }}>
            <div className={`shutdown-overlay ${isShuttingDown ? 'active' : ''}`}></div>
            <div className={`ee-bomb-reboot-flash ${bombRebooting ? 'active' : ''}`}></div>
            {bombDialogOpen && (
              <SystemBombDialog onRestart={handleBombRestart} onDismiss={handleBombDismiss} />
            )}
            {toasterActive && (
              <ToasterOverlay entities={toasterEntities} onDismiss={dismissToasters} />
            )}
            {screensaverActive && (
              <StarfieldScreensaver onDismiss={dismissScreensaver} />
            )}
            {forceQuitOpen && (
              <ForceQuitModal windows={windows} closeWindow={closeWindow} onClose={closeForceQuit} />
            )}
            <div className="mac-menubar" style={{ justifyContent: 'space-between' }}>
              <div style={{ display: 'flex', alignItems: 'center', height: '100%' }}>
                <div
                  className="mac-menu-item"
                  style={{ padding: '0 12px', position: 'relative' }}
                  onPointerDown={handleAppleLogoPointerDown}
                >
                  <AppleLogo />
                  {appleMenuOpen && (
                    <div className="mac-dropdown" onPointerDown={(e) => e.stopPropagation()}>
                      <div className="mac-dropdown-item" onPointerDown={handleAboutThisMac}>About This Macintosh</div>
                      <div className="mac-dropdown-divider"></div>
                      <div className="mac-dropdown-item" onPointerDown={handleAboutClick}>About Tyler Geddes</div>
                      <div className="mac-dropdown-divider"></div>
                      <div className="mac-dropdown-item" onPointerDown={handleSoundToggle}>
                        <span className="ee-sound-menu-item-check">{soundEnabled ? '✓' : ''}</span>Sound
                      </div>
                      <div className="mac-dropdown-divider"></div>
                      <div className="mac-dropdown-item" onPointerDown={handleStartScreensaver}>Start Screen Saver</div>
                      <div className="mac-dropdown-divider"></div>
                      <div className="mac-dropdown-item" onPointerDown={handleShutDownClick}>Shut Down</div>
                    </div>
                  )}
                </div>
                <div 
                  className="mac-menu-item"
                  style={{ position: 'relative' }}
                  onPointerDown={(e) => { e.stopPropagation(); closeAllMenus(); setAppsMenuOpen(!appsMenuOpen); }}
                >
                  Apps
                  {appsMenuOpen && (
                    <div className="mac-dropdown" onPointerDown={(e) => e.stopPropagation()}>
                      <div className="mac-dropdown-item" onPointerDown={handleAboutTrailKit}>About TrailKit</div>
                      <div className="mac-dropdown-item" onPointerDown={handleAboutPlanFit}>About PlanFit</div>
                    </div>
                  )}
                </div>
                <div 
                  className="mac-menu-item"
                  style={{ position: 'relative' }}
                  onPointerDown={(e) => { e.stopPropagation(); closeAllMenus(); setToolsMenuOpen(!toolsMenuOpen); }}
                >
                  Tools
                  {toolsMenuOpen && (
                    <div className="mac-dropdown" onPointerDown={(e) => e.stopPropagation()}>
                      <div className="mac-dropdown-item" onPointerDown={handleJDExtractor}>JD-Extractor</div>
                      <div className="mac-dropdown-item" onPointerDown={handleResumeCatalogue}>Resume Catalogue</div>
                    </div>
                  )}
                </div>
                <div 
                  className="mac-menu-item"
                  style={{ position: 'relative' }}
                  onPointerDown={(e) => { e.stopPropagation(); closeAllMenus(); setThoughtsMenuOpen(!thoughtsMenuOpen); }}
                >
                  Thoughts
                  {thoughtsMenuOpen && (
                    <div className="mac-dropdown" onPointerDown={(e) => e.stopPropagation()}>
                      <div className="mac-dropdown-item" onPointerDown={handleBuildingTrailKit}>Building TrailKit</div>
                    </div>
                  )}
                </div>
              </div>
              <div style={{ padding: '0 10px', fontSize: '14px', fontWeight: 'bold', display: 'flex', alignItems: 'center', height: '100%' }}>
                {clockTime}
              </div>
            </div>

            {[...desktopItems, ...floppyVisibleItems].map(item => (
              <DesktopIcon
                key={item.id}
                item={item}
                isDesktop={true}
                selectedIcon={selectedIcon}
                setSelectedIcon={setSelectedIcon}
                openWindow={openWindow}
                poofedIds={poofedIds}
                smugIds={smugIds}
                smugFadingIds={smugFadingIds}
                dizzyIds={dizzyIds}
                onTrashMove={item.type === 'trash' ? handleTrashMove : undefined}
                onTrashRelease={item.type === 'trash' ? handleTrashRelease : undefined}
                onShake={handleShake}
              />
            ))}

            {poofAnimAt && (
              <PoofCloud x={poofAnimAt.x} y={poofAnimAt.y} />
            )}

            {windows.map(win => (
              <DraggableWindow
                key={win.id}
                win={win}
                bringToFront={bringToFront}
                closeWindow={closeWindow}
                handleDesktopClick={handleDesktopClick}
                selectedIcon={selectedIcon}
                setSelectedIcon={setSelectedIcon}
                openWindow={openWindow}
                allWindows={windows}
              />
            ))}
          </div>
        );
      }

      const root = ReactDOM.createRoot(document.getElementById('root'));
      root.render(<App />);
    </script>
</body>
</html>