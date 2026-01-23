/**
 * Coords - Centralized coordinate system utilities
 * 
 * This class provides a single source of truth for all coordinate conversions
 * and naming conventions used throughout the game.
 * 
 * COORDINATE SYSTEMS:
 * 
 * 1. WORLD SPACE (3D game world)
 *    - X axis: West (-) to East (+)
 *    - Y axis: Down (-) to Up (+)  
 *    - Z axis: South (-) to North (+)
 *    - Origin: Center of starting area
 * 
 * 2. CANVAS 2D (texture drawing)
 *    - X axis: Left (0) to Right (max)
 *    - Y axis: Top (0) to Bottom (max)  <- INVERTED from world!
 *    - Origin: Top-left corner
 * 
 * 3. TILE GRID (ground tile indices)
 *    - gridX: West (-) to East (+)
 *    - gridZ: South (-) to North (+)
 *    - Each tile is groundTileSize (50m) square
 * 
 * CORNER NAMING (Compass-based, consistent across all systems):
 *    'nw' = Northwest = low X, high Z in world = top-left visually
 *    'ne' = Northeast = high X, high Z in world = top-right visually
 *    'sw' = Southwest = low X, low Z in world = bottom-left visually
 *    'se' = Southeast = high X, low Z in world = bottom-right visually
 * 
 * LEGACY CORNER NAMES (avoid in new code):
 *    'tl' = top-left = 'nw'
 *    'tr' = top-right = 'ne'
 *    'bl' = bottom-left = 'sw'
 *    'br' = bottom-right = 'se'
 */
const Coords = {
    // Corner definitions - single source of truth
    CORNERS: {
        nw: { x: -1, z: 1,  legacy: 'tl', name: 'Northwest', canvasX: 0, canvasY: 0 },
        ne: { x: 1,  z: 1,  legacy: 'tr', name: 'Northeast', canvasX: 1, canvasY: 0 },
        sw: { x: -1, z: -1, legacy: 'bl', name: 'Southwest', canvasX: 0, canvasY: 1 },
        se: { x: 1,  z: -1, legacy: 'br', name: 'Southeast', canvasX: 1, canvasY: 1 }
    },

    // Legacy to compass mapping
    LEGACY_TO_COMPASS: {
        'tl': 'nw', 'tr': 'ne', 'bl': 'sw', 'br': 'se'
    },
    
    // Compass to legacy mapping
    COMPASS_TO_LEGACY: {
        'nw': 'tl', 'ne': 'tr', 'sw': 'bl', 'se': 'br'
    },

    /**
     * Get corner key from sign values (direction from a reference point)
     * @param {number} signX - +1 if target is East of reference, -1 if West
     * @param {number} signZ - +1 if target is North of reference, -1 if South
     * @param {boolean} useLegacy - Return legacy names ('tl') instead of compass ('nw')
     * @returns {string} Corner key
     */
    cornerFromSigns(signX, signZ, useLegacy = true) {
        let corner;
        if (signX >= 0 && signZ >= 0) corner = 'ne';
        else if (signX < 0 && signZ >= 0) corner = 'nw';
        else if (signX >= 0 && signZ < 0) corner = 'se';
        else corner = 'sw';
        
        return useLegacy ? this.COMPASS_TO_LEGACY[corner] : corner;
    },

    /**
     * Get sign values from corner key
     * @param {string} corner - Corner key (compass or legacy)
     * @returns {{x: number, z: number}} Sign values
     */
    signsFromCorner(corner) {
        // Convert legacy to compass if needed
        const compass = this.LEGACY_TO_COMPASS[corner] || corner;
        const def = this.CORNERS[compass];
        return def ? { x: def.x, z: def.z } : { x: 0, z: 0 };
    },

    /**
     * Convert world position to canvas position (for texture drawing)
     * @param {number} worldX - World X coordinate
     * @param {number} worldZ - World Z coordinate  
     * @param {number} tileSize - Size of tile in world units
     * @param {number} texSize - Size of texture in pixels
     * @param {number} tileCenterX - World X of tile center
     * @param {number} tileCenterZ - World Z of tile center
     * @returns {{x: number, y: number}} Canvas coordinates
     */
    worldToCanvas(worldX, worldZ, tileSize, texSize, tileCenterX, tileCenterZ) {
        // World: X increases East, Z increases North
        // Canvas: X increases Right (same as world X), Y increases Down (opposite of world Z)
        const halfTile = tileSize / 2;
        const relX = worldX - (tileCenterX - halfTile); // Distance from west edge
        const relZ = (tileCenterZ + halfTile) - worldZ; // Distance from north edge (inverted)
        
        return {
            x: (relX / tileSize) * texSize,
            y: (relZ / tileSize) * texSize
        };
    },

    /**
     * Convert canvas position to world position
     * @param {number} canvasX - Canvas X coordinate
     * @param {number} canvasY - Canvas Y coordinate
     * @param {number} tileSize - Size of tile in world units
     * @param {number} texSize - Size of texture in pixels
     * @param {number} tileCenterX - World X of tile center
     * @param {number} tileCenterZ - World Z of tile center
     * @returns {{x: number, z: number}} World coordinates
     */
    canvasToWorld(canvasX, canvasY, tileSize, texSize, tileCenterX, tileCenterZ) {
        const halfTile = tileSize / 2;
        const relX = (canvasX / texSize) * tileSize;
        const relZ = (canvasY / texSize) * tileSize;
        
        return {
            x: (tileCenterX - halfTile) + relX,
            z: (tileCenterZ + halfTile) - relZ  // Invert Y back to Z
        };
    },

    /**
     * Get canvas corner position for a given corner key
     * @param {string} corner - Corner key (compass or legacy)
     * @param {number} texSize - Texture size in pixels
     * @param {number} margin - Optional margin from edge
     * @returns {{x: number, y: number}} Canvas coordinates
     */
    getCanvasCorner(corner, texSize, margin = 0) {
        const compass = this.LEGACY_TO_COMPASS[corner] || corner;
        const def = this.CORNERS[compass];
        if (!def) return { x: 0, y: 0 };
        
        return {
            x: def.canvasX === 0 ? margin : texSize - margin,
            y: def.canvasY === 0 ? margin : texSize - margin
        };
    },

    /**
     * Get the four tiles that share a corner at a given world position
     * @param {number} worldX - World X of the corner point
     * @param {number} worldZ - World Z of the corner point
     * @param {number} tileSize - Size of each tile
     * @returns {Array<{gridX: number, gridZ: number, corner: string}>} Tile info with which corner touches the point
     */
    getTilesAtCorner(worldX, worldZ, tileSize) {
        // Find the four tile centers around this point
        const gridX = Math.floor(worldX / tileSize);
        const gridZ = Math.floor(worldZ / tileSize);
        
        // The point is at the corner of 4 tiles
        // Determine which tiles based on where in the grid cell the point falls
        const fracX = (worldX / tileSize) - gridX;
        const fracZ = (worldZ / tileSize) - gridZ;
        
        const tiles = [];
        
        // Tile to the SW (gridX, gridZ) - point is at its NE corner
        tiles.push({ gridX: gridX, gridZ: gridZ, corner: 'ne' });
        // Tile to the SE (gridX+1, gridZ) - point is at its NW corner  
        tiles.push({ gridX: gridX + 1, gridZ: gridZ, corner: 'nw' });
        // Tile to the NW (gridX, gridZ+1) - point is at its SE corner
        tiles.push({ gridX: gridX, gridZ: gridZ + 1, corner: 'se' });
        // Tile to the NE (gridX+1, gridZ+1) - point is at its SW corner
        tiles.push({ gridX: gridX + 1, gridZ: gridZ + 1, corner: 'sw' });
        
        return tiles;
    },

    /**
     * Convert legacy corner name to compass
     * @param {string} legacy - Legacy corner ('tl', 'tr', 'bl', 'br')
     * @returns {string} Compass corner ('nw', 'ne', 'sw', 'se')
     */
    toCompass(legacy) {
        return this.LEGACY_TO_COMPASS[legacy] || legacy;
    },

    /**
     * Convert compass corner name to legacy
     * @param {string} compass - Compass corner ('nw', 'ne', 'sw', 'se')
     * @returns {string} Legacy corner ('tl', 'tr', 'bl', 'br')
     */
    toLegacy(compass) {
        return this.COMPASS_TO_LEGACY[compass] || compass;
    }
};

// Make available globally
window.Coords = Coords;

