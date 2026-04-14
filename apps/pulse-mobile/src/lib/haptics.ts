import { Haptics, ImpactStyle } from '@capacitor/haptics'

export async function hapticLight(): Promise<void> {
  try {
    await Haptics.impact({ style: ImpactStyle.Light })
  } catch {
    /* not on device or permission */
  }
}
