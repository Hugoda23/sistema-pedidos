import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { InvalidPhoneError, normalizePhone, toChatId } from '../src/phone.js';

/**
 * Un teléfono mal normalizado no rebota: el aviso le llega a otra persona. Por
 * eso lo que no se reconoce falla en vez de arreglarse a ojo.
 */
describe('normalizePhone', () => {
  const gt = (raw) => normalizePhone(raw, '502');

  it('le pone el país al número nacional, venga como venga escrito', () => {
    for (const raw of ['5512-3456', '5512 3456', '55123456', '(5512) 3456']) {
      assert.equal(gt(raw), '50255123456');
    }
  });

  it('respeta el que ya trae país', () => {
    for (const raw of ['+502 5512 3456', '502-5512-3456', '0050255123456']) {
      assert.equal(gt(raw), '50255123456');
    }
  });

  /* Un número de otro país escrito con «+» no se toca: anteponerle 502 daría
     un número guatemalteco que existe y es de otra persona. */
  it('no le mete el 502 a un número extranjero', () => {
    assert.equal(gt('+1 305 555 0142'), '13055550142');
    assert.equal(gt('+34 600 123 456'), '34600123456');
  });

  it('rechaza lo que no alcanza a ser un número', () => {
    for (const raw of ['', '   ', 'no tiene', '1234', '+502 12']) {
      assert.throws(() => gt(raw), InvalidPhoneError, `debería rechazar «${raw}»`);
    }
  });

  it('rechaza el vacío con un motivo claro', () => {
    assert.throws(() => gt(null), (error) => {
      assert.ok(error instanceof InvalidPhoneError);
      assert.match(error.message, /teléfono registrado/i);
      return true;
    });
  });

  it('arma el id de chat de WhatsApp', () => {
    assert.equal(toChatId('50255123456'), '50255123456@c.us');
  });
});
