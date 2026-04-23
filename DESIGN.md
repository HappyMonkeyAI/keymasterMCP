# Keymaster Design System (DESIGN.md)

This document defines the visual language for Keymaster MCP.

## 🎨 Color Palette
- **Background (Surface)**: `#030712` (Deep Space)
- **Card / Elevation**: `rgba(17, 24, 39, 0.6)` (Obsidian Glass)
- **Primary**: `#3b82f6` (Vivid Blue)
- **Primary Glow**: `rgba(59, 130, 246, 0.4)`
- **Secondary**: `#a855f7` (Purple)
- **Border**: `rgba(255, 255, 255, 0.08)`
- **Text Main**: `#f8fafc` (Slate 50)
- **Text Dim**: `#94a3b8` (Slate 400)
- **Danger**: `#ef4444` (Red)
- **Success**: `#22c55e` (Emerald)

## 🖋 Typography
- **Primary Font**: `Inter`, `-apple-system`, `sans-serif`
- **Monospace**: `JetBrains Mono`, `monospace`
- **Weights**: 300 (Light), 400 (Regular), 600 (Semibold)

## 📐 Spacing & Layout
- **Containers**: Max-width `1200px` for detail pages, `1400px` for dashboards.
- **Radius**: `1rem` (16px) for cards, `0.75rem` (12px) for buttons/inputs.
- **Padding**: Consistent `2rem` for sections.

## ✨ Visual Effects
- **Backdrop Blur**: `blur(20px)`
- **Borders**: Thin 1px solid with low opacity.
- **Shadows**: Soft, deep shadows for depth.
- **Gradients**: Subtle linear gradients for headers and primary buttons.

## 🧱 Component Patterns

### Cards (Glassmorphism)
```css
background: rgba(17, 24, 39, 0.6);
backdrop-filter: blur(20px);
border: 1px solid rgba(255, 255, 255, 0.08);
border-radius: 1rem;
```

### Tables
- Headers should be semi-transparent and uppercase.
- Rows should have a subtle hover state.
- No vertical borders.

### Buttons
- **Primary**: Gradient background with glow.
- **Ghost**: Transparent with subtle border.
- **Danger**: Red text or red glow.
