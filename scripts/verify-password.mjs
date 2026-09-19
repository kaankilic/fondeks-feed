import { scrypt } from "node:crypto";
import { promisify } from "node:util";
import { timingSafeEqual } from "node:crypto";

const scryptAsync = promisify(scrypt);

const [, , password, digest] = process.argv;
if (!password || !digest) {
    process.stdout.write("false");
    process.exit(0);
}

const [scheme, salt, hash] = digest.split(":");
if (scheme !== "scrypt" || !salt || !hash) {
    process.stdout.write("false");
    process.exit(0);
}

const expected = Buffer.from(hash, "hex");
const actual = await scryptAsync(password, salt, expected.length);

process.stdout.write(timingSafeEqual(expected, actual) ? "true" : "false");
