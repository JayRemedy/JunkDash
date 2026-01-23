/**
 * LevelManager - Procedural level generation based on volume
 */
class LevelManager {
    constructor() {
        this.currentLevelData = null;
        
        // Constants
        this.TRUCK_CAPACITY_YD3 = 25;
        this.VOLUME_PER_LEVEL_YD3 = 25 / 16; // 1.5625 yd³ per level
        this.CUBIC_YARDS_TO_CUBIC_METERS = 0.764555;
        
        // Item templates pool - volume in cubic meters
        // packingFactor: how much of the bounding box is actual material (tables have legs with empty space)
        this.itemTemplates = [
            // Small boxes - solid, pack efficiently
            { type: 'box', name: 'Small Box', icon: '📦', size: { x: 0.4, y: 0.4, z: 0.4 }, weight: 5, packingFactor: 1.0 },
            { type: 'box', name: 'Small Box', icon: '📦', size: { x: 0.35, y: 0.35, z: 0.35 }, weight: 4, packingFactor: 1.0 },
            
            // Medium boxes
            { type: 'box', name: 'Medium Box', icon: '📦', size: { x: 0.5, y: 0.5, z: 0.5 }, weight: 8, packingFactor: 1.0 },
            { type: 'box', name: 'Medium Box', icon: '📦', size: { x: 0.6, y: 0.5, z: 0.6 }, weight: 10, packingFactor: 1.0 },
            { type: 'box', name: 'Tall Box', icon: '📦', size: { x: 0.4, y: 0.7, z: 0.4 }, weight: 8, packingFactor: 1.0 },
            
            // Large boxes
            { type: 'box', name: 'Large Box', icon: '📦', size: { x: 0.7, y: 0.6, z: 0.7 }, weight: 14, packingFactor: 1.0 },
            { type: 'box', name: 'Large Box', icon: '📦', size: { x: 0.8, y: 0.6, z: 0.8 }, weight: 16, packingFactor: 1.0 },
            
            // Chairs - legs with open space between
            { type: 'chair', name: 'Chair', icon: '🪑', size: { x: 0.5, y: 0.9, z: 0.5 }, weight: 8, packingFactor: 0.4 },
            { type: 'chair', name: 'Armchair', icon: '🪑', size: { x: 0.7, y: 0.85, z: 0.7 }, weight: 15, packingFactor: 0.5 },
            
            // Tables - thin tabletop with legs, lots of empty space underneath (can stack small items under)
            // Dining table counts as 5.5% of truck volume (empty space)
            { type: 'table', name: 'Dining Table', icon: '🪵', size: { x: 3.95, y: 0.9, z: 1.65 }, weight: 50, packingFactor: 0.4, volumeOverrideYd3: 25 * 0.06 },
            { type: 'table', name: 'Coffee Table', icon: '🪵', size: { x: 1.4, y: 0.45, z: 0.8 }, weight: 22, packingFactor: 0.45 },
            
            // Couches - mostly solid with cushions
            // Loveseat sized to 7.5% of a 25 yd³ truck
            { type: 'couch', name: 'Loveseat', icon: '🛋️', size: { x: 1.7, y: 0.88, z: 0.95 }, weight: 35, packingFactor: 1.0, volumeOverrideYd3: 25 * 0.075 },
            { type: 'couch', name: 'Couch', icon: '🛋️', size: { x: 1.8, y: 0.8, z: 0.9 }, weight: 45, packingFactor: 0.7 },
            // Refrigerator (upright) and Freezer (sideways), both 7.5% of truck volume
            { type: 'fridge', name: 'Refrigerator', icon: '🧊', size: { x: 0.8, y: 1.8, z: 1.0 }, weight: 50, packingFactor: 1.0, volumeOverrideYd3: 25 * 0.075 },
            { type: 'fridge', name: 'Freezer', icon: '🧊', size: { x: 0.8, y: 1.0, z: 1.8 }, weight: 50, packingFactor: 1.0, volumeOverrideYd3: 25 * 0.075 },
            
            // Appliances - solid boxes
            { type: 'fridge', name: 'Mini Fridge', icon: '🧊', size: { x: 0.5, y: 0.7, z: 0.5 }, weight: 25, packingFactor: 0.95 },
            { type: 'washer', name: 'Washer', icon: '🫧', size: { x: 0.65, y: 0.85, z: 0.65 }, weight: 55, packingFactor: 0.95 },
            
            // Bedroom - dressers have drawers but mostly solid
            { type: 'dresser', name: 'Dresser', icon: '🗄️', size: { x: 1.0, y: 1.0, z: 0.5 }, weight: 35, packingFactor: 0.85 },
            { type: 'dresser', name: 'Tall Dresser', icon: '🗄️', size: { x: 0.8, y: 1.3, z: 0.5 }, weight: 40, packingFactor: 0.85 },
            { type: 'mattress', name: 'Twin Mattress', icon: '🛏️', size: { x: 1.0, y: 0.25, z: 1.9 }, weight: 20, packingFactor: 0.9 },
            { type: 'mattress', name: 'Queen Mattress', icon: '🛏️', size: { x: 1.5, y: 0.28, z: 2.0 }, weight: 30, packingFactor: 0.9 },
            
            // Misc - lamps are mostly air but awkward to pack around
            { type: 'lamp', name: 'Floor Lamp', icon: '🪔', size: { x: 0.3, y: 1.4, z: 0.3 }, weight: 5, packingFactor: 0.25 },
            { type: 'lamp', name: 'Table Lamp', icon: '🪔', size: { x: 0.25, y: 0.5, z: 0.25 }, weight: 3, packingFactor: 0.3 },
        ];
        
        // Calculate effective volume for each template (bounding box * packing factor)
        this.itemTemplates.forEach(item => {
            const boundingVolume = item.size.x * item.size.y * item.size.z;
            item.volumeM3 = boundingVolume * (item.packingFactor || 1.0);
            if (item.volumeOverrideYd3) {
                item.volumeM3 = item.volumeOverrideYd3 * this.CUBIC_YARDS_TO_CUBIC_METERS;
            } else if (item.volumeOverrideM3) {
                item.volumeM3 = item.volumeOverrideM3;
            }
        });
    }
    
    // Get target volume in cubic meters for a level
    getTargetVolumeM3(levelNumber) {
        const targetYd3 = this.VOLUME_PER_LEVEL_YD3 * levelNumber;
        return targetYd3 * this.CUBIC_YARDS_TO_CUBIC_METERS;
    }
    
    // Generate items for a level
    generateLevel(levelNumber) {
        // Level 1: Single loveseat as tutorial
        if (levelNumber === 1) {
            const loveseat = this.itemTemplates.find(t => t.name === 'Loveseat');
            const item = this.createItemFromTemplate(loveseat, 1, 0);
            return {
                name: 'Tutorial',
                targetVolumeYd3: item.volumeM3 / this.CUBIC_YARDS_TO_CUBIC_METERS,
                items: [item]
            };
        }

        // Level 2: Dining table + 2 chairs
        if (levelNumber === 2) {
            const diningTable = this.itemTemplates.find(t => t.name === 'Dining Table');
            const chair = this.itemTemplates.find(t => t.name === 'Chair');
            const items = [];
            let itemCounter = 0;
            if (diningTable) items.push(this.createItemFromTemplate(diningTable, 2, itemCounter++));
            if (chair) {
                items.push(this.createItemFromTemplate(chair, 2, itemCounter++));
                items.push(this.createItemFromTemplate(chair, 2, itemCounter++));
            }
            const totalVolume = items.reduce((sum, item) => sum + (item.volumeM3 || 0), 0);
            return {
                name: this.getLevelName(levelNumber),
                targetVolumeYd3: totalVolume / this.CUBIC_YARDS_TO_CUBIC_METERS,
                items
            };
        }

        // Level 3: Refrigerator + Freezer + Dining Table + Large Box
        if (levelNumber === 3) {
            const refrigerator = this.itemTemplates.find(t => t.name === 'Refrigerator');
            const freezer = this.itemTemplates.find(t => t.name === 'Freezer');
            const diningTable = this.itemTemplates.find(t => t.name === 'Dining Table');
            const largeBox = this.itemTemplates.find(t => t.name === 'Large Box');
            const items = [];
            let itemCounter = 0;
            if (refrigerator) items.push(this.createItemFromTemplate(refrigerator, 3, itemCounter++));
            if (freezer) items.push(this.createItemFromTemplate(freezer, 3, itemCounter++));
            if (diningTable) items.push(this.createItemFromTemplate(diningTable, 3, itemCounter++));
            if (largeBox) items.push(this.createItemFromTemplate(largeBox, 3, itemCounter++));
            const totalVolume = items.reduce((sum, item) => sum + (item.volumeM3 || 0), 0);
            return {
                name: this.getLevelName(levelNumber),
                targetVolumeYd3: totalVolume / this.CUBIC_YARDS_TO_CUBIC_METERS,
                items
            };
        }
        
        const targetVolume = this.getTargetVolumeM3(levelNumber);
        const items = [];
        let currentVolume = 0;
        let itemCounter = 0;
        
        // Seed random based on level for consistency
        const seededRandom = this.seededRandom(levelNumber * 12345);
        
        // Target item count: level 2 is hand-crafted, then scale by 2
        // Level 3: 4 items, Level 4: 6 items, etc.
        const targetItemCount = Math.max(2, 2 + Math.floor((levelNumber - 2) * 2));
        
        // Calculate ideal average volume per item
        const avgVolumePerItem = targetVolume / targetItemCount;
        
        // Get available templates and sort by volume
        const availableTemplates = this.getAvailableTemplates(levelNumber);
        
        // For level 2, hand-craft: 1 loveseat + 2 chairs
        if (levelNumber === 2) {
            const loveseat = availableTemplates.find(t => t.name === 'Loveseat');
            const chair = availableTemplates.find(t => t.name === 'Chair');
            if (loveseat) {
                items.push(this.createItemFromTemplate(loveseat, levelNumber, itemCounter++));
                currentVolume += loveseat.volumeM3;
            }
            if (chair) {
                items.push(this.createItemFromTemplate(chair, levelNumber, itemCounter++));
                currentVolume += chair.volumeM3;
                items.push(this.createItemFromTemplate(chair, levelNumber, itemCounter++));
                currentVolume += chair.volumeM3;
            }
        }
        
        // Try to pick items close to the average volume needed
        for (let i = items.length; i < targetItemCount && currentVolume < targetVolume * 0.95; i++) {
            const remainingVolume = targetVolume - currentVolume;
            const remainingItems = targetItemCount - i;
            const idealVolume = remainingVolume / remainingItems;
            
            // Find templates closest to the ideal volume
            const scoredTemplates = availableTemplates.map(t => ({
                template: t,
                score: Math.abs(t.volumeM3 - idealVolume)
            })).sort((a, b) => a.score - b.score);
            
            // Pick from the top 3 closest matches randomly for variety
            const topChoices = scoredTemplates.slice(0, Math.min(3, scoredTemplates.length));
            const choice = topChoices[Math.floor(seededRandom() * topChoices.length)];
            
            if (choice && currentVolume + choice.template.volumeM3 <= targetVolume * 1.1) {
                items.push(this.createItemFromTemplate(choice.template, levelNumber, itemCounter++));
                currentVolume += choice.template.volumeM3;
            }
        }
        
        // Ensure at least one item
        if (items.length === 0) {
            const smallBox = this.itemTemplates[0];
            items.push(this.createItemFromTemplate(smallBox, levelNumber, 0));
        }
        
        
        return {
            name: this.getLevelName(levelNumber),
            targetVolumeYd3: targetVolume / this.CUBIC_YARDS_TO_CUBIC_METERS,
            items: items
        };
    }
    
    // Get available templates based on level (introduce bigger items later)
    getAvailableTemplates(levelNumber) {
        // Start with just boxes, gradually add more item types
        let templates = this.itemTemplates.filter(t => t.type === 'box');
        
        if (levelNumber >= 2) {
            // Add chairs and a couch for a more substantial load
            templates = templates.concat(this.itemTemplates.filter(t => t.type === 'chair' || t.type === 'couch'));
        }
        
        if (levelNumber >= 3) {
            // Add tables, lamps, and appliances
            templates = templates.concat(this.itemTemplates.filter(t =>
                t.type === 'table' || t.type === 'lamp' || t.type === 'fridge'
            ));
        }
        
        if (levelNumber >= 4) {
            // Add small furniture
            templates = templates.concat(this.itemTemplates.filter(t =>
                t.type === 'couch' && t.volumeM3 < 1.5
            ));
        }
        
        if (levelNumber >= 5) {
            // Add dressers
            templates = templates.concat(this.itemTemplates.filter(t => t.type === 'dresser'));
        }
        
        if (levelNumber >= 6) {
            // Add all couches
            templates = templates.concat(this.itemTemplates.filter(t => 
                t.type === 'couch' && t.volumeM3 >= 1.5
            ));
        }
        
        if (levelNumber >= 7) {
            // Add appliances
            templates = templates.concat(this.itemTemplates.filter(t => 
                t.type === 'fridge' || t.type === 'washer'
            ));
        }
        
        if (levelNumber >= 8) {
            // Add mattresses
            templates = templates.concat(this.itemTemplates.filter(t => t.type === 'mattress'));
        }
        
        return templates;
    }
    
    createItemFromTemplate(template, levelNumber, index) {
        const overrideYd3 = template.volumeOverrideYd3;
        const overrideM3 = template.volumeOverrideM3;
        const volumeM3 = overrideYd3
            ? overrideYd3 * this.CUBIC_YARDS_TO_CUBIC_METERS
            : (overrideM3 || template.volumeM3);
        return {
            id: `${template.type}${levelNumber}_${index}`,
            type: template.type,
            name: template.name,
            icon: template.icon,
            size: { ...template.size },
            weight: template.weight,
            volumeM3: volumeM3,
            volumeOverrideYd3: overrideYd3,
            volumeOverrideM3: overrideM3,
            packingFactor: template.packingFactor || 1.0
        };
    }
    
    getLevelName(levelNumber) {
        const fraction = levelNumber / 16;
        if (fraction <= 0.125) return 'Easy Start';
        if (fraction <= 0.25) return 'Getting Busy';
        if (fraction <= 0.375) return 'Quarter Load';
        if (fraction <= 0.5) return 'Half Way There';
        if (fraction <= 0.625) return 'More Than Half';
        if (fraction <= 0.75) return 'Three Quarters';
        if (fraction <= 0.875) return 'Almost Full';
        if (fraction <= 1.0) return 'Full Truck!';
        return `Overloaded ${levelNumber}`;
    }
    
    // Simple seeded random number generator
    seededRandom(seed) {
        let s = seed;
        return function() {
            s = Math.sin(s * 9999) * 10000;
            return s - Math.floor(s);
        };
    }
    
    loadLevel(levelNumber, game) {
        const level = this.generateLevel(levelNumber);
        this.currentLevelData = level;
        
        // Load item definitions
        game.itemManager.loadItems(level.items);
        game.uiManager.populateItemQueue(level.items);
    }
    
    getTotalLevels() {
        // Infinite levels, but 16 fills the truck
        return Infinity;
    }
}
