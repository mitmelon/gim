async function isLoggedIn(ctx, sessionId) {
  if (!sessionId) return false;

  const timestamp = parseInt(Math.round(+new Date()/1000).toString());
  const checkLoggedIn = await ctx.db
    .query('session')
    .filter((q) =>
      q.and(q.eq(q.field("stoken"), sessionId), q.gt(q.field("expire"), timestamp))
    )
    .unique();

  if (checkLoggedIn !== null) {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 30);
    const expire = parseInt(Math.floor(now.getTime() / 1000).toString());

    await ctx.db.patch(checkLoggedIn["_id"], { expire: expire });
    return checkLoggedIn['authToken'];
  }
  return false;
}

async function verifications(ctx, vtoken, authToken) {

  //This function generates a token that expires every 10 minutes
  const now = new Date();
  now.setMinutes(now.getMinutes() + 10);
  const expire = parseInt(Math.floor(now.getTime() / 1000).toString());
  const timestamp = parseInt(Math.round(+new Date()/1000).toString());//Measured in seconds

  const code = await createRandomString(10);
  if(code !== null || code !== 'null'){
    const response = await ctx.db.insert("verifications", { vtoken: vtoken, authToken: authToken, code: code, expire: expire, dateUpdated: timestamp});
    return {docId: response, code: code};
  }
  throw new Error('Code cannot be generated');
}

async function createRandomString(length) {
  const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  let result = "";
  const randomArray = new Uint8Array(length);
  crypto.getRandomValues(randomArray);
  randomArray.forEach((number) => {
    result += chars[number % chars.length];
  });
  return result;
}

module.exports = { isLoggedIn, verifications, createRandomString};