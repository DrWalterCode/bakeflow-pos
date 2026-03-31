[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$PayloadBase64,

    [string]$PrinterName = '',

    [string]$JobName = 'BakeFlow Receipt'
)

$ErrorActionPreference = 'Stop'

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public static class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPWStr)]
        public string pDocName;

        [MarshalAs(UnmanagedType.LPWStr)]
        public string pOutputFile;

        [MarshalAs(UnmanagedType.LPWStr)]
        public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern bool OpenPrinter(string src, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, Int32 level, DOCINFOA di);

    [DllImport("winspool.Drv", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, byte[] data, Int32 buf, out Int32 pcWritten);
}
"@

try {
    if ([string]::IsNullOrWhiteSpace($PrinterName)) {
        $defaultPrinter = Get-CimInstance Win32_Printer | Where-Object { $_.Default } | Select-Object -First 1
        if (-not $defaultPrinter) {
            throw 'No default printer is configured in Windows.'
        }
        $PrinterName = $defaultPrinter.Name
    }

    $payload = [Convert]::FromBase64String($PayloadBase64)
    $handle = [IntPtr]::Zero

    if (-not [RawPrinterHelper]::OpenPrinter($PrinterName, [ref]$handle, [IntPtr]::Zero)) {
        throw "OpenPrinter failed with Win32 error $([Runtime.InteropServices.Marshal]::GetLastWin32Error())."
    }

    $doc = New-Object RawPrinterHelper+DOCINFOA
    $doc.pDocName = $JobName
    $doc.pDataType = 'RAW'

    if (-not [RawPrinterHelper]::StartDocPrinter($handle, 1, $doc)) {
        throw "StartDocPrinter failed with Win32 error $([Runtime.InteropServices.Marshal]::GetLastWin32Error())."
    }

    if (-not [RawPrinterHelper]::StartPagePrinter($handle)) {
        throw "StartPagePrinter failed with Win32 error $([Runtime.InteropServices.Marshal]::GetLastWin32Error())."
    }

    $written = 0
    if (-not [RawPrinterHelper]::WritePrinter($handle, $payload, $payload.Length, [ref]$written)) {
        throw "WritePrinter failed with Win32 error $([Runtime.InteropServices.Marshal]::GetLastWin32Error())."
    }

    [RawPrinterHelper]::EndPagePrinter($handle) | Out-Null
    [RawPrinterHelper]::EndDocPrinter($handle) | Out-Null
    [RawPrinterHelper]::ClosePrinter($handle) | Out-Null

    [pscustomobject]@{
        success       = $true
        printer_name  = $PrinterName
        bytes_written = $written
    } | ConvertTo-Json -Compress
    exit 0
} catch {
    if ($handle -ne [IntPtr]::Zero) {
        try { [RawPrinterHelper]::ClosePrinter($handle) | Out-Null } catch {}
    }

    [pscustomobject]@{
        success = $false
        error   = $_.Exception.Message
    } | ConvertTo-Json -Compress
    exit 1
}
