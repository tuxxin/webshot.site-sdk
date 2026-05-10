# Cross-language quickstart examples

The same one-screen capture-and-save script, written in each supported language. Pick the one that matches your stack.

| File | Language | Run |
|---|---|---|
| [`basic-capture.js`](./basic-capture.js)  | Node 18+ | `node basic-capture.js` |
| [`basic-capture.py`](./basic-capture.py)  | Python 3.9+ | `python basic-capture.py` |
| [`basic-capture.php`](./basic-capture.php) | PHP 8.1+ | `php basic-capture.php` |

Each script:

1. Builds a client.
2. Captures `https://example.com` as a PNG.
3. Writes `example.png` to disk.
4. Handles throttle errors gracefully.

For per-language examples (batch capture, async, browser), see each SDK's own `examples/` folder:

- [`../js/examples/`](../js/examples)
- [`../python/examples/`](../python/examples)
- [`../php/examples/`](../php/examples)
