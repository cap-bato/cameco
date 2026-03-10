$path = "c:\Users\navar\my-projects\cameco\docs\issues\TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md"
$bytes = [System.IO.File]::ReadAllBytes($path)
$content = [System.Text.Encoding]::UTF8.GetString($bytes)

# Convert mojibake back to proper UTF-8 bytes, then to proper chars, then to ASCII
# The mojibake happens because UTF-8 box-drawing was decoded as Win-1252
# We'll fix this by treating the string as UTF-8 encoded

$utf8 = [System.Text.Encoding]::UTF8
$win1252 = [System.Text.Encoding]::GetEncoding(1252)

# Re-encode to fix mojibake: treat current string as Win-1252, get UTF-8 bytes
$win1252Bytes = $win1252.GetBytes($content)
$fixedContent = $utf8.GetString($win1252Bytes)

# Now replace proper Unicode box-drawing with ASCII
$fixedContent = $fixedContent.Replace([char]0x250C, '+')  # ┌
$fixedContent = $fixedContent.Replace([char]0x2510, '+')  # ┐
$fixedContent = $fixedContent.Replace([char]0x2514, '+')  # └
$fixedContent = $fixedContent.Replace([char]0x2518, '+')  # ┘
$fixedContent = $fixedContent.Replace([char]0x251C, '+')  # ├
$fixedContent = $fixedContent.Replace([char]0x2524, '+')  # ┤
$fixedContent = $fixedContent.Replace([char]0x252C, '+')  # ┬
$fixedContent = $fixedContent.Replace([char]0x2534, '+')  # ┴
$fixedContent = $fixedContent.Replace([char]0x253C, '+')  # ┼
$fixedContent = $fixedContent.Replace([char]0x2500, '-')  # ─
$fixedContent = $fixedContent.Replace([char]0x2502, '|')  # │

[System.IO.File]::WriteAllText($path, $fixedContent, $utf8)
Write-Host "Conversion complete!"
