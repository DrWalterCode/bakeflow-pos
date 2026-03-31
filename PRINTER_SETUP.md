# Receipt Printer Setup Notes

Updated: 2026-03-31

## Hardware

- Receipt printer: `Xprinter XP-80TS`
- Connection: `USB`
- Cash drawer: connected through the receipt printer

## Windows Driver Installed

- Driver installer used: `Xprinter Driver Setup V7.77`
- Options selected during install:
  - `Windows 10`
  - `USB`
  - `XP-80`

## Current Windows Printer Queue

At the time of setup, Windows created this printer queue:

- Printer name: `XP-90`
- Driver name: `XP-90`
- Port: `USB001`

Note:

- Even though the physical printer is `XP-80TS`, the installed queue showed up as `XP-90`.
- This should be checked again if printing issues continue.

## BakeFlow Printing Mode

Recommended current setup:

- BakeFlow now prints receipts by sending `RAW ESC/POS` directly to the configured Windows printer queue
- The POS `Print` button should go straight to `XP-90` without opening Edge print preview
- The app sends its own cut command at the end of the receipt
- Browser print remains only as a fallback if the Windows print helper fails
- If no queue name is saved in BakeFlow settings, keep `XP-90` set as the default Windows printer on this terminal

Important:

- Direct receipt printing happens on the machine that is running the BakeFlow PHP site
- If a cashier opens BakeFlow from another machine while the web server is running somewhere else, the print job will go to the server machine, not the cashier machine
- For each terminal that must print to its own local USB Xprinter, run BakeFlow locally on that terminal or otherwise make sure the terminal is the machine executing PHP

## Setting Up Another Machine

For a new Windows terminal, do this:

1. Install the Xprinter driver and confirm Windows can print a self-test
2. Plug in the cash drawer through the printer
3. Make sure the printer queue appears in Windows, for example `XP-90`
4. Open BakeFlow on that machine
5. Go to `Admin > Settings`
6. In `Windows Printer Queue Name`, enter the exact Windows queue name, for example `XP-90`
7. If you leave that field blank, BakeFlow will use the default Windows printer instead
8. Turn on `Open cash drawer on cash and split receipts` if you want the drawer pulse enabled
9. Optionally turn on `Auto-print receipt after payment`
10. Save settings
11. Run a live test sale and confirm print, cut, and drawer open

What BakeFlow needs in its own settings:

- `Windows Printer Queue Name`:
  optional but recommended when the machine has more than one printer
- `Open cash drawer on cash and split receipts`:
  enables the ESC/POS drawer pulse
- `Auto-print receipt after payment`:
  prints automatically after a sale without pressing the print button

What BakeFlow does not need:

- It does not need QZ Tray
- It does not need a browser printer popup for normal receipt printing
- It does not need a special printer setting inside the POS if the Windows queue name is already saved

## Testing History (2026-03-31)

### Problem 1: Blank receipt from Windows

- First attempt to print from Windows produced a completely blank receipt
- Root cause: **thermal paper was loaded the wrong way** (non-printable side facing the print head)
- Fix: flipped the paper roll so the thermally-coated side faces the print head

### Problem 2: Self-test confirmed hardware works

Ran the printer self-test (hold FEED while powering on). Results:

- Self-test printed **clearly and in bold black text**
- Auto-cutter fired correctly at the end of the self-test
- Confirmed the printer hardware, thermal head, and cutter all work

Self-test revealed these printer settings:

| Setting | Value |
|---|---|
| Printing width | `72mm` |
| Character per line | `46/64` |
| Density level | `5 (max=8)` |
| Default code page | `Page0` |
| Black mark mode | `No` |
| USB printing | `Yes` |

### Problem 3: Browser receipts were faint, clipped, and too long

After fixing the paper, BakeFlow receipts printed via browser but had issues:

- **Faint/light text** because the browser sends a raster image instead of native thermal text
- **Prices and totals missing on the right** because the old receipt layout was too wide for the printer's `72mm` printable area
- **Too much blank paper after the receipt** because the browser was effectively printing a full page instead of a tightly-sized receipt
- **Receipt # and date were truncated** because the old layout did not handle narrow thermal widths well

### BakeFlow fix applied

Updated BakeFlow in two stages:

1. Browser receipt rendering was tightened:
   - dedicated receipt print window
   - measured receipt height
   - `70mm` content width
   - darker text and tighter spacing
   - wrapped item labels so totals stay visible

2. Direct Windows printing was added:
   - the POS now posts the transaction to a local Windows RAW print helper
   - the helper sends native ESC/POS text straight to `XP-90`
   - the receipt ends with a printer cut command instead of relying on full-page browser printing
   - this removes the large blank section that came from Edge print preview using a fixed paper length

### Direct Print Verification

Verified on `2026-03-31`:

- POS status changed to `Printed on XP-90.`
- Windows spooler recorded job `TXN-BF4C6842D7-1774946885`
- Queue: `XP-90`
- Pages: `1`

### Recommended Windows Printer Preferences for XP-90

These settings should be configured in **Settings > Printers > XP-90 > Printing Preferences**:

| Setting | Recommended Value |
|---|---|
| Print density | `7` or `8` |
| Auto cut | `Enabled` |
| Paper type | `Roll paper` or `Receipt` |
| Paper width | `80mm` |
| Mode | `Receipt / Continuous` |
| Black mark | `Off` |
| Borders | `Off` |

### What still needs verification

- [ ] Reprint a BakeFlow receipt after the direct-print change
- [ ] Confirm the right-side amounts are fully visible
- [ ] Confirm the trailing blank paper is now gone on the physical print
- [ ] Confirm the ESC/POS cut command fires reliably after each receipt

## Self-Test Procedure

1. Turn the printer off
2. Make sure paper is loaded
3. Hold the `FEED` button
4. Turn the printer on while still holding `FEED`
5. Keep holding for about `3-5 seconds`
6. Release when printing starts

Interpretation:

- If the self-test prints correctly, the hardware is fine and the issue is in driver or print settings
- If the self-test is blank, check paper orientation, paper type, or hardware

## Thermal Paper Check

Scratch the paper with a fingernail or coin:

- If it turns dark, that is the printable side
- Load the roll so the printable side faces the print head

## If Reinstalling the Driver

Use:

- `Windows 10`
- `USB`
- `XP-80`

Do not intentionally choose `XP-90`, `XP-58`, or `XP-76`.

If the Xprinter driver still behaves badly, try the fallback:

- Remove the current printer queue
- Recreate the printer on `USB001`
- Use Windows built-in driver: `Generic / Text Only`
