' Jalankan Print Agent TANPA jendela CMD (tersembunyi di background).
' Pakai file ini (bukan start-agent.bat) di folder Startup supaya kasir tidak
' terganggu jendela hitam. Agent tetap jalan; hentikan lewat Task Manager
' (cari "node.exe") kalau perlu.
Set shell = CreateObject("WScript.Shell")
' 0 = jendela disembunyikan, False = jangan tunggu selesai.
shell.CurrentDirectory = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
shell.Run "node agent.js", 0, False
