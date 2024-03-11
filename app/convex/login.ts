import { mutation, query } from "./_generated/server";
import { v } from "convex/values";
import * as bcrypt from "bcryptjs";
import * as loginFunctions from './LoginFunctions';


export const login = mutation({
  args: { email: v.string(), password: v.string(), fingerprint: v.string(), browser: v.string(), os: v.string(), ip: v.string(), device: v.string() },
  handler: async (ctx, args) => {
    if (!args.email && !args.password && !args.fingerprint) throw new Error('Invalid authorization values')

    const auth = await ctx.db
      .query('auth_user')
      .filter((q) => q.eq(q.field("email"), args.email))
      .unique();
    if (auth !== null) {
      //Check password match
      const isHash = bcrypt.compareSync(args.password, auth['password']);
      if (isHash) {
        //Check if user not blocked
        if (auth['status'] !== 'active') {
          return 'Sorry your account is not active.';
        }
        //create session
        const token = crypto.randomUUID();
        const now = new Date();
        now.setMinutes(now.getMinutes() + 30);
        const expire = parseInt(Math.floor(now.getTime() / 1000).toString()); //expires in 30 minutes time
        const timestamp = parseInt(Math.round(+new Date() / 1000).toString());//current time

        await ctx.db.insert("session", { stoken: token, authToken: auth['authToken'], fingerprint: args.fingerprint, browser: args.browser, os: args.os, ip: args.ip, device: args.device, expire: expire, dateUpdated: timestamp });
        return { token: token, userData: auth };
      }

    }
    return false;
  },
})

export const register = mutation({
  args: { name: v.string(), email: v.string(), country: v.string(), fingerprint: v.string(), password: v.string() },
  handler: async (ctx, args) => {
    try {
      //Check if this email already used
      const emailExists = await ctx.db
        .query('auth_user')
        .filter((q) => q.eq(q.field("email"), args.email))
        .unique();

      if (emailExists !== null) {
        return 'Sorry this email already exists';
      }

      //Hash the password - Never store plain passwords
      const salt = bcrypt.genSaltSync(10);
      const hash = bcrypt.hashSync(args.password, salt);

      //Get current time
      const timestamp = parseInt(Math.round(+new Date() / 1000).toString());

      const token = crypto.randomUUID(); //Generates safe ID
      const response = await ctx.db.insert("auth_user", { authToken: token, name: args.name, email: args.email, country: args.country, fingerprint: args.fingerprint, password: hash, salt: salt, status: 'pending', dateAdded: timestamp, dateUpdated: timestamp });

      if (response !== null) {
        const vtoken = crypto.randomUUID();
        //generate email verification code and return the code to the application for email sending.
        const vres = await loginFunctions.verifications(ctx, vtoken, token);
        return { authToken: token, code: vres.code, userDocId: response, verifyId: vres.docId };
      }
      return false;
    } catch (e) {
      return e;
    }
  },
})

export const isLoggedIn = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    //Query this function if you want to check if user is loggedIn
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    return response;
  },
});

export const verificationStatus = query({
  args: { code: v.string(), authToken: v.string() },
  handler: async (ctx, args) => {
    if (!args.code || !args.authToken) return false;
    const timestamp = parseInt(Math.round(+new Date() / 1000).toString());
    const verify = await ctx.db
      .query('verifications')
      .withIndex("by_token", (q) => q.eq("authToken", args.authToken))
      .filter((q) =>
        q.and(q.eq(q.field("code"), args.code), q.gt(q.field("expire"), timestamp))
      ).unique();
    if (verify !== null) {
      return true;
    }
    return false;
  }

});

//Delete data from auth_user document
export const deleteFromAuthUser = mutation({
  args: { id: v.id("auth_user") },
  handler: async (ctx, args) => {
    await ctx.db.delete(args.id);
  }
});

//Delete data from verifications document
export const deleteFromVerifications = mutation({
  args: { id: v.id("verifications") },
  handler: async (ctx, args) => {
    await ctx.db.delete(args.id);
  }
});

export const activateUserAccount = mutation({
  args: { id: v.id("auth_user") },
  handler: async (ctx, args) => {
    const timestamp = parseInt(Math.round(+new Date() / 1000).toString());
    return await ctx.db.patch(args.id, { status: 'active', dateUpdated: timestamp });
  }
});

export const sessionDestroy = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const timestamp = parseInt(Math.round(+new Date() / 1000).toString());
    const checkLoggedIn = await ctx.db
      .query('session')
      .filter((q) =>
        q.and(q.eq(q.field("stoken"), args.sessionId), q.gt(q.field("expire"), timestamp))
      )
      .unique();
    if (checkLoggedIn !== null) {
      await ctx.db.delete(checkLoggedIn._id);
    }
  }
});