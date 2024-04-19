Set UAC = CreateObject("Shell.Application")
UAC.ShellExecute "cmd.exe", "/c {{ updateScript }}", "", "runas", 0