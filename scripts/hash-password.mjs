import { scrypt, randomBytes } from "node:crypto";
import { promisify } from "node:util";

const scryptAsync = promisify(scrypt);

const KEY_LENGTH = 64;
const SALT_LENGTH = 16;

const [, , password] = process.argv;
if (!password) {
    process.exit(1);
}

const salt = randomBytes(SALT_LENGTH).toString("hex");
const derived = await scryptAsync(password, salt, KEY_LENGTH);

process.stdout.write(`scrypt:${salt}:${derived.toString("hex")}`);
