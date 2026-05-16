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
    <title>Mac OS Directory Viewer</title>
    
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
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
      const { useState, useEffect, useRef } = React;

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
          <text x="100" y="60" fontFamily="Chicago, 'Trebuchet MS', sans-serif" fontSize="10" textAnchor="middle" fill="#000">Version 1.0.4</text>
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

          {/* Background & Frame */}
          <rect width="400" height="250" fill="#FFF"/>
          <rect x="8" y="8" width="384" height="234" fill="url(#planfit-grid)"/>
          <rect x="2" y="2" width="396" height="246" fill="none" stroke="#000" strokeWidth="4"/>
          <rect x="8" y="8" width="384" height="234" fill="none" stroke="#000" strokeWidth="1"/>

          {/* Left Panel / Clipboard */}
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

          {/* Right Panel / Giant Stopwatch */}
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

          {/* Giant Hex Dumbbell overlay */}
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

          {/* App Title Plate */}
          <rect x="20" y="20" width="160" height="50" fill="#FFF" stroke="#000" strokeWidth="2" rx="4"/>
          <rect x="22" y="22" width="156" height="46" fill="url(#dither-dark)"/>
          <rect x="25" y="25" width="150" height="40" fill="#FFF" stroke="#000" strokeWidth="1"/>
          <text x="100" y="48" fontFamily="Chicago, 'Trebuchet MS', sans-serif" fontSize="20" fontWeight="bold" textAnchor="middle" fill="#000" letterSpacing="2">PLANFIT</text>
          <text x="100" y="60" fontFamily="Chicago, 'Trebuchet MS', sans-serif" fontSize="9" textAnchor="middle" fill="#000">Version 2.1</text>
        </svg>
      );


      const AppleLogo = () => (
        <img 
          src="https://tgeddes.com/images/favicon-32x32.png" 
          alt="Menu Logo" 
          style={{ width: '16px', height: '16px', objectFit: 'contain' }} 
        />
      );

      // Moved completely outside App to preserve state between renders
      const DesktopIcon = ({ item, isDesktop, selectedIcon, setSelectedIcon, openWindow }) => {
        const isSelected = selectedIcon === item.id;
        const [pos, setPos] = useState({ x: item.x || 0, y: item.y || 0 });
        const [tipped, setTipped] = useState(false);
        const dragOffset = useRef({ x: 0, y: 0 });

        useEffect(() => {
          setPos({ x: item.x || 0, y: item.y || 0 });
        }, [item.x, item.y]);

        const handlePointerDown = (e) => {
          e.stopPropagation();
          setSelectedIcon(item.id);
          if (isDesktop) {
            e.currentTarget.setPointerCapture(e.pointerId);
            dragOffset.current = { x: e.clientX - pos.x, y: e.clientY - pos.y };
          }
        };

        const handlePointerMove = (e) => {
          if (!isDesktop) return;
          if (e.currentTarget.hasPointerCapture(e.pointerId)) {
            setPos({ x: e.clientX - dragOffset.current.x, y: e.clientY - dragOffset.current.y });
          }
        };

        const handlePointerUp = (e) => {
          if (isDesktop && e.currentTarget.hasPointerCapture(e.pointerId)) {
            e.currentTarget.releasePointerCapture(e.pointerId);
          }
        };

        const handleDoubleClick = (e) => {
          e.stopPropagation();
          if (item.type === 'trash') {
            setTipped(!tipped);
            return;
          }
          openWindow(item);
        };

        return (
          <div 
            className={`mac-icon-container ${isSelected ? 'selected' : ''}`}
            style={isDesktop ? { left: pos.x, top: pos.y } : { position: 'relative', margin: '15px', display: 'inline-flex' }}
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerUp}
            onPointerCancel={handlePointerUp}
            onDoubleClick={handleDoubleClick}
          >
            {item.type === 'folder' && <FolderIcon selected={isSelected} />}
            {item.type === 'file' && <FileIcon selected={isSelected} />}
            {item.type === 'trash' && <TrashIcon selected={isSelected} tipped={tipped} />}
            {item.id === 'app-trailkit' && <TKIcon selected={isSelected} />}
            {item.id === 'app-planfit' && <PFIcon selected={isSelected} />}
            <div className="mac-icon-label">{item.name}</div>
          </div>
        );
      };

      // Moved completely outside App to preserve drag/resize state safely
      const DraggableWindow = ({ win, bringToFront, closeWindow, handleDesktopClick, selectedIcon, setSelectedIcon, openWindow }) => {
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
              {win.type === 'text' ? (
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

      function App() {
        const [windows, setWindows] = useState([]);
        const [selectedIcon, setSelectedIcon] = useState(null);
        const [zIndexCounter, setZIndexCounter] = useState(10);
        const [desktopItems, setDesktopItems] = useState([]);
        const [appleMenuOpen, setAppleMenuOpen] = useState(false);
        const [appsMenuOpen, setAppsMenuOpen] = useState(false);
        const [isShuttingDown, setIsShuttingDown] = useState(false);

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

              // Open default windows on load
              setWindows([
                {
                  id: 'trailkit-story',
                  name: 'TrailKit Story',
                  type: 'iframe',
                  url: 'trailkit/trailkitstory/index.html',
                  x: Math.max(50, window.innerWidth - 850), // Open on the right
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
                  x: 50, // Open About on the left
                  y: 50,
                  zIndex: 11, // Place on top initially
                  width: 400,
                  height: 200
                }
              ]);
              setZIndexCounter(11);
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

          setZIndexCounter(prev => {
            const newZ = prev + 1;
            const newWindow = {
              ...item,
              x: 50 + (windows.length * 20),
              y: 50 + (windows.length * 20),
              zIndex: newZ,
              width: 450,
              height: 250
            };
            setWindows(windowsPrev => [...windowsPrev, newWindow]);
            return newZ;
          });
        };

        const closeWindow = (id) => {
          setWindows(windows => windows.filter(w => w.id !== id));
        };

        const handleDesktopClick = (e) => {
          setAppleMenuOpen(false);
          setAppsMenuOpen(false);
          if (typeof e.target.className === 'string') {
            if (e.target.className.includes('mac-desktop') || e.target.className.includes('mac-window-content')) {
              setSelectedIcon(null);
            }
          }
        };

        const handleAboutClick = (e) => {
          e.stopPropagation();
          setAppleMenuOpen(false);
          setAppsMenuOpen(false);
          openWindow({
            id: 'about-tyler',
            name: 'About Tyler Geddes',
            type: 'text',
            content: "By day, I’m a GTM Enablement leader, improving revenue metrics through cohesive global learning strategies and programs. By night, I’m an AI-native problem solver, building the software I’ve always wanted to use. I understand high-level SaaS execution and want to create the cutting-edge tools that make it actually work. I also just want to make some really cool, fun stuff."
          });
        };

        const handleAboutTrailKit = (e) => {
          e.stopPropagation();
          setAppleMenuOpen(false);
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
          setAppleMenuOpen(false);
          openWindow({
            id: 'planfit-splash',
            name: 'About PlanFit',
            type: 'splash',
            width: 400,
            height: 270
          });
        };

        const handleShutDownClick = (e) => {
          e.stopPropagation();
          setAppleMenuOpen(false);
          setAppsMenuOpen(false);
          setIsShuttingDown(true);
          setTimeout(() => {
            window.location.href = 'https://www.tgeddes.com';
          }, 1500);
        };

        return (
          <div className="mac-desktop" onPointerDown={handleDesktopClick}>
            <div className={`shutdown-overlay ${isShuttingDown ? 'active' : ''}`}></div>
            <div className="mac-menubar">
              <div 
                className="mac-menu-item" 
                style={{ padding: '0 12px', position: 'relative' }}
                onPointerDown={(e) => { e.stopPropagation(); setAppleMenuOpen(!appleMenuOpen); }}
              >
                <AppleLogo />
                {appleMenuOpen && (
                  <div className="mac-dropdown" onPointerDown={(e) => e.stopPropagation()}>
                    <div className="mac-dropdown-item" onPointerDown={handleAboutClick}>About Tyler Geddes</div>
                    <div className="mac-dropdown-divider"></div>
                    <div className="mac-dropdown-item" onPointerDown={handleShutDownClick}>Shut Down</div>
                  </div>
                )}
              </div>
              <div 
                className="mac-menu-item"
                style={{ position: 'relative' }}
                onPointerDown={(e) => { e.stopPropagation(); setAppleMenuOpen(false); setAppsMenuOpen(!appsMenuOpen); }}
              >
                Apps
                {appsMenuOpen && (
                  <div className="mac-dropdown" onPointerDown={(e) => e.stopPropagation()}>
                    <div className="mac-dropdown-item" onPointerDown={handleAboutTrailKit}>About TrailKit</div>
                    <div className="mac-dropdown-item" onPointerDown={handleAboutPlanFit}>About PlanFit</div>
                  </div>
                )}
              </div>
              <div className="mac-menu-item">Tools</div>
              <div className="mac-menu-item">Thoughts</div>
            </div>

            {desktopItems.map(item => (
              <DesktopIcon 
                key={item.id} 
                item={item} 
                isDesktop={true} 
                selectedIcon={selectedIcon}
                setSelectedIcon={setSelectedIcon}
                openWindow={openWindow}
              />
            ))}

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