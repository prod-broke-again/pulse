import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.achpp.pulse',
  appName: 'Pulse',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
  },
}

export default config
