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

      const TrashIcon = ({ selected }) => (
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="6" y="8" width="20" height="22" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2"/>
          <path d="M4 8H28" stroke="#000" strokeWidth="2"/>
          <path d="M12 8V4H20V8" fill={selected ? "#000" : "#FFF"} stroke="#000" strokeWidth="2"/>
        </svg>
      );

      const AppleLogo = () => (
        <svg width="16" height="16" viewBox="0 0 16 16" fill="black" xmlns="http://www.w3.org/2000/svg">
          <path d="M10.5 4.5C10.5 3.5 11.5 2.5 12.5 2.5C12.5 4 11.5 5 10.5 4.5ZM13.5 11.5C12.5 13.5 10.5 14.5 9.5 14.5C8.5 14.5 7.5 13.5 6 13.5C4.5 13.5 3.5 14.5 2.5 14.5C1.5 14.5 0 12.5 0 9.5C0 6.5 2 4.5 4 4.5C5 4.5 6 5.5 7 5.5C8 5.5 9 4.5 11 4.5C12.5 4.5 13.5 5.5 14 6.5C12 7.5 12 10.5 14 11.5C13.8 11.5 13.5 11.5 13.5 11.5Z" />
        </svg>
      );

      function App() {
        const [windows, setWindows] = useState([]);
        const [selectedIcon, setSelectedIcon] = useState(null);
        const [zIndexCounter, setZIndexCounter] = useState(10);
        const [desktopItems, setDesktopItems] = useState([]);

        useEffect(() => {
          fetch('?api=1')
            .then(response => response.json())
            .then(data => {
              setDesktopItems([
                data, 
                { id: 'trash', name: 'Trash', type: 'trash', x: window.innerWidth - 80, y: window.innerHeight - 80, children: [] }
              ]);
            })
            .catch(error => console.error("Error fetching FS:", error));
          
          const handleResize = () => {
            setDesktopItems(items => items.map(item => 
              item.id === 'trash' ? { ...item, x: window.innerWidth - 80, y: window.innerHeight - 80 } : item
            ));
          };
          window.addEventListener('resize', handleResize);
          return () => window.removeEventListener('resize', handleResize);
        }, []);

        const bringToFront = (id) => {
          setZIndexCounter(prev => prev + 1);
          setWindows(prev => prev.map(w => w.id === id ? { ...w, zIndex: zIndexCounter + 1 } : w));
        };

        const openWindow = (item) => {
          // If it's a file, open in a new tab
          if (item.type === 'file') {
            window.open(item.url, '_blank');
            return;
          }

          // Otherwise, handle as a folder window
          if (item.type === 'trash') {
            item = { ...item, type: 'folder', children: [] };
          }
          
          const existingWindow = windows.find(w => w.id === item.id);
          if (existingWindow) {
            bringToFront(item.id);
            return;
          }

          const newZIndex = zIndexCounter + 1;
          setZIndexCounter(newZIndex);

          const newWindow = {
            ...item,
            x: 50 + (windows.length * 20),
            y: 50 + (windows.length * 20),
            zIndex: newZIndex,
            width: 450,
            height: 250
          };

          setWindows(prev => [...prev, newWindow]);
        };

        const closeWindow = (id) => {
          setWindows(windows.filter(w => w.id !== id));
        };

        const handleDesktopClick = (e) => {
          if (typeof e.target.className === 'string') {
            if (e.target.className.includes('mac-desktop') || e.target.className.includes('mac-window-content')) {
              setSelectedIcon(null);
            }
          }
        };

        const Icon = ({ item, isDesktop }) => {
          const isSelected = selectedIcon === item.id;
          const [[x, y], setPosition] = useState([item.x || 0, item.y || 0]);
          const isDragging = useRef(false);
          const dragOffset = useRef({ x: 0, y: 0 });

          const handleMouseDown = (e) => {
            e.stopPropagation();
            setSelectedIcon(item.id);
            if (isDesktop) {
              isDragging.current = true;
              dragOffset.current = { x: e.clientX - x, y: e.clientY - y };
              document.addEventListener('mousemove', handleMouseMove);
              document.addEventListener('mouseup', handleMouseUp);
            }
          };

          const handleMouseMove = (e) => {
            if (!isDragging.current) return;
            setPosition([e.clientX - dragOffset.current.x, e.clientY - dragOffset.current.y]);
          };

          const handleMouseUp = () => {
            isDragging.current = false;
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
          };

          const handleDoubleClick = (e) => {
            e.stopPropagation();
            openWindow(item);
          };

          return (
            <div 
              className={`mac-icon-container ${isSelected ? 'selected' : ''}`}
              style={isDesktop ? { left: x, top: y } : { position: 'relative', margin: '15px', display: 'inline-flex' }}
              onMouseDown={handleMouseDown}
              onDoubleClick={handleDoubleClick}
            >
              {item.type === 'folder' && <FolderIcon selected={isSelected} />}
              {item.type === 'file' && <FileIcon selected={isSelected} />}
              {item.type === 'trash' && <TrashIcon selected={isSelected} />}
              <div className="mac-icon-label">{item.name}</div>
            </div>
          );
        };

        const Window = ({ win }) => {
          const [pos, setPos] = useState({ x: win.x, y: win.y });
          const isDragging = useRef(false);
          const dragOffset = useRef({ x: 0, y: 0 });

          const handleMouseDown = (e) => {
            bringToFront(win.id);
            isDragging.current = true;
            dragOffset.current = { x: e.clientX - pos.x, y: e.clientY - pos.y };
            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
          };

          const handleMouseMove = (e) => {
            if (!isDragging.current) return;
            setPos({ x: e.clientX - dragOffset.current.x, y: e.clientY - dragOffset.current.y });
          };

          const handleMouseUp = () => {
            isDragging.current = false;
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
          };

          return (
            <div 
              className="mac-window"
              style={{ left: pos.x, top: pos.y, zIndex: win.zIndex, width: win.width, height: win.height }}
              onMouseDown={() => bringToFront(win.id)}
            >
              <div className="mac-titlebar" onMouseDown={handleMouseDown}>
                <div className="mac-close-box" onMouseDown={(e) => { e.stopPropagation(); closeWindow(win.id); }}></div>
                <div className="mac-titlebar-text">{win.name}</div>
              </div>
              
              <div className="mac-window-content" onClick={handleDesktopClick}>
                <div style={{ display: 'flex', flexWrap: 'wrap', alignContent: 'flex-start' }}>
                  {win.children && win.children.map(child => (
                    <Icon key={child.id} item={child} isDesktop={false} />
                  ))}
                  {(!win.children || win.children.length === 0) && (
                    <div style={{ color: '#666', width: '100%', textAlign: 'center', marginTop: '20px' }}>
                      0 items
                    </div>
                  )}
                </div>
              </div>
            </div>
          );
        };

        return (
          <div className="mac-desktop" onMouseDown={handleDesktopClick}>
            <div className="mac-menubar">
              <div className="mac-menu-item" style={{ padding: '0 12px' }}><AppleLogo /></div>
              <div className="mac-menu-item">File</div>
              <div className="mac-menu-item">Edit</div>
              <div className="mac-menu-item">View</div>
              <div className="mac-menu-item">Special</div>
            </div>

            {desktopItems.map(item => (
              <Icon key={item.id} item={item} isDesktop={true} />
            ))}

            {windows.map(win => (
              <Window key={win.id} win={win} />
            ))}
          </div>
        );
      }

      const root = ReactDOM.createRoot(document.getElementById('root'));
      root.render(<App />);
    </script>
</body>
</html>
