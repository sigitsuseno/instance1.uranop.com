import sys
sys.path.insert(0, r'C:\Users\Sigit\AppData\Local\hermes\images')

try:
    from PIL import Image
    img = Image.open(r'C:\Users\Sigit\AppData\Local\hermes\images\clip_20260610_173014_1.png')
    print(f"Format: {img.format}")
    print(f"Size: {img.size}")
    print(f"Mode: {img.mode}")
    print(f"Info: {img.info}")
except ImportError:
    print("PIL not available")
    try:
        import struct
        with open(r'C:\Users\Sigit\AppData\Local\hermes\images\clip_20260610_173014_1.png', 'rb') as f:
            header = f.read(8)
            if header[:8] == b'\x89PNG\r\n\x1a\n':
                print("Valid PNG file")
                # Read IHDR
                f.read(4)  # length
                f.read(4)  # 'IHDR'
                width = struct.unpack('>I', f.read(4))[0]
                height = struct.unpack('>I', f.read(4))[0]
                print(f"Width: {width}, Height: {height}")
            f.seek(0, 2)
            print(f"File size: {f.tell()} bytes")
    except Exception as e:
        print(f"Error: {e}")
