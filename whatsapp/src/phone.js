/**
 * Los teléfonos llegan como los escribieron en el sistema:
 * «5512-3456», «+502 5512 3456», «(502) 5512-3456». WhatsApp quiere un número
 * a secas con el país delante, así que hay que normalizarlos antes.
 */

/** Longitudes de un número nacional guatemalteco; sirve para decidir si falta el país. */
const NATIONAL_LENGTH = 8;

export class InvalidPhoneError extends Error {
  constructor(message) {
    super(message);
    this.name = 'InvalidPhoneError';
  }
}

/**
 * Devuelve el número en formato internacional, sólo dígitos («50255123456»).
 *
 * El país se antepone únicamente cuando el número tiene la longitud de uno
 * nacional: un número que ya viene con prefijo —de un número extranjero, o
 * escrito con «+502»— se respeta tal cual. Adivinar de más es peor que fallar:
 * un número mal armado no rebota, le llega a otra persona.
 *
 * @param {string} raw El teléfono tal como está guardado.
 * @param {string} countryCode Prefijo por defecto, sin «+».
 * @returns {string} Dígitos listos para WhatsApp.
 * @throws {InvalidPhoneError} si no queda nada reconocible.
 */
export function normalizePhone(raw, countryCode) {
  if (typeof raw !== 'string' || raw.trim() === '') {
    throw new InvalidPhoneError('No hay teléfono registrado.');
  }

  const hadPlus = raw.trim().startsWith('+');
  // Los separadores que se usan al escribir un teléfono no son parte de él.
  const digits = raw.replace(/\D/g, '');

  if (digits === '') {
    throw new InvalidPhoneError(`«${raw}» no parece un número de teléfono.`);
  }

  if (hadPlus || digits.startsWith(countryCode) || digits.length > NATIONAL_LENGTH) {
    // Un 00 delante es la otra forma de escribir el «+».
    const international = digits.replace(/^00/, '');

    if (international.length < NATIONAL_LENGTH) {
      throw new InvalidPhoneError(`«${raw}» tiene muy pocos dígitos para ser un número.`);
    }

    return international;
  }

  if (digits.length < NATIONAL_LENGTH) {
    throw new InvalidPhoneError(`«${raw}» tiene muy pocos dígitos para ser un número.`);
  }

  return `${countryCode}${digits}`;
}

/** El identificador de chat que usa WhatsApp para un número personal. */
export const toChatId = (digits) => `${digits}@c.us`;
