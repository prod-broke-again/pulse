import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.achpp.pulse',
  appName: 'Pulse',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
  },
  plugins: {
    SplashScreen: {
      backgroundColor: '#ffffff',
      androidScaleType: 'CENTER_INSIDE',
    },
  },
}

export default config
