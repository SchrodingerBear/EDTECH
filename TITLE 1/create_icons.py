#!/usr/bin/env python3
"""
Create PWA icons from existing SVG
Requires PIL (Pillow): pip install Pillow
"""

from PIL import Image, ImageDraw, ImageFont
import sys
from pathlib import Path

def create_icon(size, output_path):
    """Create a simple PWA icon"""
    # Create image with gradient background
    img = Image.new('RGB', (size, size), color='#4f46e5')
    draw = ImageDraw.Draw(img)

    # Draw a simple "360" or building icon
    # Center circle
    margin = size // 8
    draw.ellipse([margin, margin, size - margin, size - margin],
                 fill='#7c3aed', outline='#a78bfa', width=size//16)

    # Draw text
    try:
        font = ImageFont.truetype("arial.ttf", size // 3)
    except:
        font = ImageFont.load_default()

    text = "360"
    bbox = draw.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]
    x = (size - text_width) // 2
    y = (size - text_height) // 2
    draw.text((x, y), text, fill='white', font=font)

    # Save
    img.save(output_path)
    print(f"Created {output_path} ({size}x{size})")

def main():
    """Create icons for PWA"""
    public_dir = Path(__file__).parent / 'public'
    public_dir.mkdir(exist_ok=True)

    print("Creating PWA icons...")

    # Create different sizes
    sizes = [192, 512]
    for size in sizes:
        output_path = public_dir / f'icon-{size}x{size}.png'
        create_icon(size, output_path)

    print("Icons created successfully!")

if __name__ == '__main__':
    try:
        main()
    except ImportError:
        print("Error: PIL (Pillow) is not installed")
        print("Install it with: pip install Pillow")
        sys.exit(1)
