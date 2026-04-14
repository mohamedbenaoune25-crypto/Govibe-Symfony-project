$i=0
$files = Get-ChildItem "public/images/wireframes/*.png"
foreach ($file in $files) {
    git add $file.FullName
    $i++
    if ($i % 50 -eq 0) {
        $batchNum = [math]::Floor($i / 50)
        git commit -m "Add wireframes batch $batchNum"
        git push origin HEAD
    }
}
if ($i % 50 -ne 0) {
    if (git status --porcelain | Select-String "public/images/wireframes/") {
        git commit -m "Add wireframes remainder"
        git push origin HEAD
    }
}
