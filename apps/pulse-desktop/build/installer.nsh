!macro customInit
  ; Best-effort: close old app instance before NSIS starts file replacement.
  nsExec::ExecToLog 'taskkill /F /T /IM "Pulse.exe"'
  Sleep 600
!macroend

