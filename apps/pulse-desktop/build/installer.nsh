!macro customInit
  ; Best-effort: close old app instance before NSIS starts file replacement.
  nsExec::ExecToLog 'taskkill /F /T /IM "Pulse.exe"'
  Sleep 700

  ; Aggressive cleanup of previous install dir to avoid stale-file deletion failures (NSIS code 2).
  StrCpy $0 0
cleanup_loop:
  IntOp $0 $0 + 1
  nsExec::ExecToLog 'cmd /C if exist "$INSTDIR" attrib -R -S -H "$INSTDIR\*" /S /D'
  nsExec::ExecToLog 'cmd /C if exist "$INSTDIR" rmdir /S /Q "$INSTDIR"'
  IfFileExists "$INSTDIR\*.*" cleanup_retry cleanup_done

cleanup_retry:
  IntCmp $0 4 cleanup_done cleanup_wait cleanup_wait

cleanup_wait:
  Sleep 900
  Goto cleanup_loop

cleanup_done:
!macroend

