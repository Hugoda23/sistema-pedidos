// Parche para whatsapp-web.js 1.34.7: desde la actualizacion de WhatsApp Web de
// septiembre de 2026, todo envio con archivo (PDF, imagen) falla con
// "Data passed to getter must include an id property (it's how we memoize)".
// El MediaData trae un __x_id interno que choca con el id del mensaje nuevo.
// Es la correccion del PR upstream wwebjs/whatsapp-web.js#201923, que aun no sale
// en una version publicada. Se quita cuando haya una version que lo traiga.
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'node_modules', 'whatsapp-web.js', 'src', 'util', 'Injected', 'Utils.js');
const anchor = "        // Bot's won't reply if canonicalUrl is set (linking)";
const fix = `        // Parche (copiado de taller-sis) (wwebjs#201923): el __x_id del MediaData no debe
        // pasar al mensaje, o WhatsApp Web rechaza todo envio con archivo.
        if (message.__x_id) {
            delete message.__x_id;
        }

`;

let src = fs.readFileSync(file, 'utf8');

if (src.includes('delete message.__x_id')) {
    console.log('[patch] whatsapp-web.js ya trae la correccion de __x_id');
    process.exit(0);
}

if (!src.includes(anchor)) {
    // Falla la construccion a proposito: sin el parche los PDF no salen y nadie
    // se entera hasta que un cliente reclama.
    console.error('[patch] No se encontro donde aplicar la correccion de __x_id en whatsapp-web.js. Revise la version.');
    process.exit(1);
}

src = src.replace(anchor, fix + anchor);
fs.writeFileSync(file, src);
console.log('[patch] Correccion de __x_id aplicada a whatsapp-web.js');
