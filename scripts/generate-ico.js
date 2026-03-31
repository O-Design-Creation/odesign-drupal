const { default: pngToIco } = require('png-to-ico');
const fs = require('fs');
const path = require('path');

const IMAGES_DIR = path.join(__dirname, '../web/themes/custom/odesign_base/images');

pngToIco([
  path.join(IMAGES_DIR, 'favicon-16x16.png'),
  path.join(IMAGES_DIR, 'favicon-32x32.png'),
  path.join(IMAGES_DIR, 'favicon-48x48.png'),
]).then(buf => {
  fs.writeFileSync(path.join(IMAGES_DIR, 'favicon.ico'), buf);
  console.log('✅ favicon.ico généré (16+32+48px)');
}).catch(err => console.error('❌', err.message));
