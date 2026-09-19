import { initializeApp } from 'firebase/app'

const firebaseConfig = {
  apiKey: 'AIzaSyB-nKM5RfstmvGF0fo2IrWiTtA-G5_yv4I',
  authDomain: 'wedding-flower-decorasi.firebaseapp.com',
  databaseURL: 'https://wedding-flower-decorasi-default-rtdb.firebaseio.com',
  projectId: 'wedding-flower-decorasi',
  storageBucket: 'wedding-flower-decorasi.firebasestorage.app',
  messagingSenderId: '125956093579',
  appId: '1:125956093579:web:abdefe470abbad1cc3868e',
  measurementId: 'G-FSSVLJG6F8',
  clientId: '125956093579-49v70n0pagru7nt2i1g2fephu1a5renn.apps.googleusercontent.com',
}

const app = initializeApp(firebaseConfig)

export default app
