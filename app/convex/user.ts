import { mutation, query } from "./_generated/server";
import { v } from "convex/values";
import * as loginFunctions from './LoginFunctions';

export const getUserData = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;

    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const auth = await ctx.db
        .query('auth_user')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (auth !== null) {
        //Only return the needed data. You should never return the password and salt alongside with the data for security reasons.
        return { authToken: auth['authToken'], name: auth['name'], email: auth['email'], country: auth['country'], fingerprint: auth['fingerprint'], status: auth['status'], dateCreated: auth['_creationTime'] };
      }
    }
    return false;
  },
});

export const getUserImage = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const image = await ctx.db
        .query('user_image')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (image !== null) {
        const url = await ctx.storage.getUrl(image.storageId);
        return url;
      }
    }
    return false;
  },
});

export const getIdentityData = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const identity = await ctx.db
        .query('identity_data')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (identity !== null) {
        return identity;
      }
    }
    return false;
  },
});

export const getAgreement = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const agreement = await ctx.db
        .query('agreement')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (agreement !== null) {
        return agreement;
      }
    }
    return false;
  },
});

export const setAgreement = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const agreement = await ctx.db
        .query('auth_user')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (agreement !== null) {
        const timestamp = parseInt(Math.round(+new Date() / 1000).toString());
        await ctx.db.insert("agreement", { authToken: response, agree: 'yes', dateUpdated: timestamp });
        return true;
      }
    }
    return false;
  },
});

export const createIdentity = mutation({
  args: { sessionId: v.string(), name: v.string(), phone: v.string(), dob: v.string(), gender: v.string(), residential_address: v.string(), residential_city: v.string(), residential_state: v.string(), residential_country: v.string(), origin_state: v.string(), origin_country: v.string(), primary_language: v.string(), about: v.string(), race: v.string() },
  handler: async (ctx, args) => {

    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const timestamp = parseInt(Math.round(+new Date() / 1000).toString());

      const tasks = await ctx.db
        .query('todo_task')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();

      //Lets check if identity already created
      const identity = await ctx.db
      .query('identity_data')
      .filter((q) => q.eq(q.field("authToken"), response))
      .unique();
      if (identity !== null) {
        //Then lets patch data and update revision counts
        const revision = identity.revision + 1;
        await ctx.db.patch(identity._id, { name: args.name, phone: args.phone, dob: args.dob, gender: args.gender, residential_address: args.residential_address, residential_city: args.residential_city, residential_state: args.residential_state, residential_country: args.residential_country, origin_state: args.origin_state, origin_country: args.origin_country, primary_language: args.primary_language, about: args.about, race: args.race, revision: revision, dateUpdated: timestamp });

        if(tasks !== null){
          await ctx.db.patch(tasks._id, { personal: 'edited', dateUpdated: timestamp});
        }
        return true;
        
      }
      //Create new Data;
      const result = await ctx.db.insert("identity_data", { authToken: response, name: args.name, phone: args.phone, dob: args.dob, gender: args.gender, residential_address: args.residential_address, residential_city: args.residential_city, residential_state: args.residential_state, residential_country: args.residential_country, origin_state: args.origin_state, origin_country: args.origin_country, primary_language: args.primary_language, about: args.about, race: args.race, revision: 0, dateUpdated: timestamp });

      //add task
      if(tasks !== null){
        await ctx.db.insert("todo_task", { authToken: response, personal: 'filled', dateUpdated: timestamp});
      }
      return result;
    }
    return false;
  },
})

export const getTodo = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const tasks = await ctx.db
        .query('todo_task')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (tasks !== null) {
        return tasks;
      }
    }
    return false;
  },
});

export const getUserCard = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const image = await ctx.db
        .query('identity_file')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (image !== null) {
        const url = await ctx.storage.getUrl(image.storageId);
        return {url: url, id: image.storageId};
      }
    }
    return false;
  },
});

export const getUserSignature = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const image = await ctx.db
        .query('signature')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (image !== null) {
        const url = await ctx.storage.getUrl(image.storageId);
        return {url: url, id: image.storageId};
      }
    }
    return false;
  },
});

export const deleteFile = mutation({
  args: { id: v.id("_storage") },
  handler: async (ctx, args) => {
    await ctx.storage.delete(args.id);
  }
});

export const verifyData = mutation({
  args: { sessionId: v.string(), id: v.string(), status: v.string(), desc: v.string(), dataMatch: v.union(v.string(), v.number()),
    timeSpent: v.union(v.string(), v.number())},
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const vdata = await ctx.db
        .query('verify_data')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
        const timestamp = parseInt(Math.round(+new Date() / 1000).toString());
      if (vdata !== null) {
        await ctx.db.patch(vdata._id, { status: args.status, descriptions: args.desc, dateUpdated: timestamp});
        return vdata.id;
      } else {
        await ctx.db.insert("verify_data", { authToken: response, id: args.id, status: args.status, descriptions: args.desc, dataMatch: args.dataMatch, timeSpent: args.timeSpent, dateCreated: timestamp, dateUpdated: timestamp });
        return args.id;
      }
    }
    return false;
  },
});

export const getVerifiedData = mutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;
    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      const vd = await ctx.db
        .query('verify_data')
        .filter((q) => q.eq(q.field("authToken"), response))
        .unique();
      if (vd !== null) {
        return vd;
      }
    }
    return false;
  },
});